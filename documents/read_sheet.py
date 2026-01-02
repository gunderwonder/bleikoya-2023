#!/usr/bin/env python3
# /// script
# requires-python = ">=3.11"
# dependencies = [
#     "google-api-python-client",
#     "google-auth",
#     "python-dotenv",
# ]
# ///
"""
Read Google Sheet from shared drive using service account.
"""

import json
import os
from pathlib import Path

from dotenv import load_dotenv
from google.oauth2 import service_account
from googleapiclient.discovery import build

# Load environment variables
env_path = Path(__file__).parent.parent / ".env"
load_dotenv(env_path)

SCOPES = [
    "https://www.googleapis.com/auth/drive.readonly",
    "https://www.googleapis.com/auth/spreadsheets.readonly",
]


def get_credentials():
    """Get Google API credentials from service account."""
    creds_path = os.getenv("GOOGLE_APPLICATION_CREDENTIALS")
    if creds_path.startswith("./"):
        creds_path = Path(__file__).parent.parent / creds_path[2:]
    else:
        creds_path = Path(creds_path)

    return service_account.Credentials.from_service_account_file(
        str(creds_path), scopes=SCOPES
    )


def find_file_in_drive(drive_service, name: str, parent_id: str = None):
    """Find a file or folder by name in the shared drive."""
    shared_drive_id = os.getenv("GOOGLE_SHARED_DRIVE_ID")

    query = f"name = '{name}'"
    if parent_id:
        query += f" and '{parent_id}' in parents"

    results = drive_service.files().list(
        q=query,
        corpora="drive",
        driveId=shared_drive_id,
        includeItemsFromAllDrives=True,
        supportsAllDrives=True,
        fields="files(id, name, mimeType, parents)",
    ).execute()

    return results.get("files", [])


def list_folder_contents(drive_service, folder_id: str):
    """List contents of a folder."""
    shared_drive_id = os.getenv("GOOGLE_SHARED_DRIVE_ID")

    results = drive_service.files().list(
        q=f"'{folder_id}' in parents",
        corpora="drive",
        driveId=shared_drive_id,
        includeItemsFromAllDrives=True,
        supportsAllDrives=True,
        fields="files(id, name, mimeType)",
    ).execute()

    return results.get("files", [])


def read_spreadsheet(sheets_service, spreadsheet_id: str):
    """Read all data from a spreadsheet."""
    # Get spreadsheet metadata
    spreadsheet = sheets_service.spreadsheets().get(
        spreadsheetId=spreadsheet_id
    ).execute()

    print(f"\nSpreadsheet: {spreadsheet['properties']['title']}")
    print("=" * 60)

    # Read each sheet
    for sheet in spreadsheet["sheets"]:
        sheet_name = sheet["properties"]["title"]
        print(f"\n## Sheet: {sheet_name}")
        print("-" * 40)

        # Get all values
        result = sheets_service.spreadsheets().values().get(
            spreadsheetId=spreadsheet_id,
            range=sheet_name
        ).execute()

        values = result.get("values", [])

        if not values:
            print("(tom)")
            continue

        # Print as table
        for row in values:
            print(" | ".join(str(cell) for cell in row))


def list_folder_tree(drive_service, folder_id: str, indent: int = 0, max_depth: int = 3):
    """Recursively list folder structure."""
    if indent >= max_depth:
        return

    contents = list_folder_contents(drive_service, folder_id)

    # Sort: folders first, then files
    folders = sorted([f for f in contents if f["mimeType"] == "application/vnd.google-apps.folder"], key=lambda x: x["name"])
    files = sorted([f for f in contents if f["mimeType"] != "application/vnd.google-apps.folder"], key=lambda x: x["name"])

    for folder in folders:
        print(f"{'  ' * indent}📁 {folder['name']}")
        list_folder_tree(drive_service, folder["id"], indent + 1, max_depth)

    # Only show file count if there are files
    if files and indent < max_depth:
        print(f"{'  ' * indent}   ({len(files)} filer)")


def main():
    credentials = get_credentials()
    drive_service = build("drive", "v3", credentials=credentials)
    sheets_service = build("sheets", "v4", credentials=credentials)

    shared_drive_id = os.getenv("GOOGLE_SHARED_DRIVE_ID")
    print(f"Shared Drive ID: {shared_drive_id}")

    # List full folder structure
    print("\n" + "=" * 60)
    print("MAPPESTRUKTUR I GOOGLE DRIVE")
    print("=" * 60 + "\n")

    list_folder_tree(drive_service, shared_drive_id, max_depth=3)

    print("\n" + "=" * 60)
    return

    # Find "020 Styret" folder
    folders_020 = find_file_in_drive(drive_service, "020 Styret")

    if not folders_020:
        print("Fant ikke mappen '020 Styret'")
        # List root contents
        print("\nInnhold i rot:")
        root_files = drive_service.files().list(
            q=f"'{shared_drive_id}' in parents",
            corpora="drive",
            driveId=shared_drive_id,
            includeItemsFromAllDrives=True,
            supportsAllDrives=True,
            fields="files(id, name, mimeType)",
        ).execute()
        for f in root_files.get("files", []):
            print(f"  {f['name']} ({f['mimeType']})")
        return

    folder_020 = folders_020[0]
    print(f"Fant '020 Styret': {folder_020['id']}")

    # List contents of 020 Styret
    print("\nInnhold i '020 Styret':")
    contents = list_folder_contents(drive_service, folder_020["id"])
    for f in contents:
        print(f"  {f['name']} ({f['mimeType']})")

    # Find the spreadsheet
    sheet_files = find_file_in_drive(
        drive_service,
        "2025-2026 Styrets ansvarsfordeling",
        folder_020["id"]
    )

    if not sheet_files:
        # Try partial match
        print("\nSøker etter spreadsheet med 'ansvarsfordeling'...")
        for f in contents:
            if "ansvarsfordeling" in f["name"].lower():
                sheet_files = [f]
                break

    if not sheet_files:
        print("Fant ikke spreadsheet")
        return

    sheet_file = sheet_files[0]
    print(f"\nFant spreadsheet: {sheet_file['name']} ({sheet_file['id']})")

    # Read the spreadsheet
    read_spreadsheet(sheets_service, sheet_file["id"])


if __name__ == "__main__":
    main()
