#!/usr/bin/env python3
"""
Migrering av medlems- og hytteinformasjon fra 900 Arkiv til 050/051-mappene.

Flytter innholdet i:
    900 Arkiv/240 Medlemmer  -> 050 Medlemmer (hyttemapper HNNNN, X00 Historikk)
    900 Arkiv/Eierskifter    -> 051 Eierskifte

Kjør med:
    uv run documents/migrate_medlemmer.py --dry-run    # Vis hva som vil skje
    uv run documents/migrate_medlemmer.py              # Utfør flytting
"""
# /// script
# requires-python = ">=3.11"
# ///

import argparse
import re
import unicodedata
from pathlib import Path

from migrate_archive import (
    DRIVE,
    KILDE,
    MÅL,
    Flytting,
    eksporter_til_csv,
    ekstraher_og_prefiks_dato,
    normaliser_filnavn,
    samle_filer,
    utfør_flyttinger,
)

# Mapper i 900 Arkiv som skal migreres
KILDEMAPPER = ["240 Medlemmer", "Eierskifter"]

MEDLEMMER = MÅL / "050 Medlemmer"
HISTORIKK = MEDLEMMER / "X00 Historikk"
EIERSKIFTE = MÅL / "051 Eierskifte"
TOMTEINNLØSNING = MÅL / "500 2020-2024 Tomteinnløsning"

# Dropbox-etterlatenskaper som ikke kan flyttes meningsfullt
HOPP_OVER_SUFFIKSER = ("-alias", ".paper")


def hyttemappe(mappenavn: str) -> str | None:
    """
    Mapper kildemappenavn som 'H63', 'H63 (1)', 'H100' til nullpaddet
    målmappe ('H0063', 'H0100') slik konvensjonen i 050 Medlemmer er.
    """
    m = re.match(r"^H(\d+)", mappenavn)
    if m:
        return f"H{int(m.group(1)):04d}"
    return None


def fiks_medlemsliste_dato(navn: str) -> str:
    """
    Normaliserer 'ÅÅÅÅ DD MM'-mønsteret brukt i gamle medlemslister.
    Hvis midtre tall > 12 tolkes det som dag og byttes med måned,
    ellers beholdes rekkefølgen som ÅÅÅÅ-MM-DD.

    '2020 14 01 Medlemsliste' -> '2020-01-14 Medlemsliste'
    '2019 06 25 Medlemsliste' -> '2019-06-25 Medlemsliste'
    """
    m = re.match(r"^(\d{4})[ .](\d{1,2})[ .](\d{1,2})\b\s*(.*)$", navn)
    if not m:
        return navn

    år, første, andre, resten = m.groups()
    if int(første) > 12:
        dag, måned = første, andre
    else:
        måned, dag = første, andre

    if not (1 <= int(måned) <= 12 and 1 <= int(dag) <= 31):
        return navn

    return f"{år}-{måned.zfill(2)}-{dag.zfill(2)} {resten}"


def fiks_ddmmyyyy(navn: str) -> str:
    """
    Flytter sammenskrevne DDMMYYYY-datoer til prefiks.
    '... eierskifter 23062025.docx' -> '2025-06-23 ... eierskifter.docx'
    """
    m = re.search(r"(?<!\d)(\d{2})(\d{2})(20\d{2})(?!\d)", navn)
    if not m:
        return navn

    dag, måned, år = m.groups()
    if not (1 <= int(måned) <= 12 and 1 <= int(dag) <= 31):
        return navn

    uten_dato = (navn[: m.start()] + navn[m.end() :]).strip()
    uten_dato = re.sub(r"\s{2,}", " ", uten_dato).replace(" .", ".")
    return f"{år}-{måned}-{dag} {uten_dato}"


def normaliser_hyttefil(navn: str) -> str:
    """
    Som normaliser_filnavn, men beholder romertall ('104 III.PDF')
    som UPPERCASE-heuristikken ellers ville gjort om til småbokstaver.
    """
    stem = navn.rsplit(".", 1)[0] if "." in navn else navn
    bokstaver = {c.upper() for c in stem if c.isalpha()}
    if bokstaver and bokstaver <= set("IVX"):
        return ekstraher_og_prefiks_dato(navn.strip())
    return normaliser_filnavn(navn)


def bestem_målmappe(kilde: Path, relativ_sti: Path) -> tuple[Path, str] | None:
    """
    Bestemmer målmappe for en fil i 240 Medlemmer eller Eierskifter.
    Returnerer (målsti, kategori) eller None hvis filen krever manuell håndtering.
    """
    filnavn = unicodedata.normalize("NFC", kilde.name)
    toppmappe = unicodedata.normalize("NFC", relativ_sti.parts[0])
    undermappe = unicodedata.normalize("NFC", relativ_sti.parts[1]) if len(relativ_sti.parts) > 2 else ""

    filnavn_lower = filnavn.lower()

    # Dropbox-alias og Paper-dokumenter kan ikke flyttes meningsfullt
    if filnavn_lower.endswith(HOPP_OVER_SUFFIKSER):
        return None

    # === EIERSKIFTER -> 051 Eierskifte ===
    if toppmappe == "Eierskifter":
        return EIERSKIFTE / normaliser_filnavn(fiks_ddmmyyyy(filnavn)), "Eierskifte"

    # === HYTTEMAPPER (H5, H63 (1), H100/H100, ...) -> 050 Medlemmer/HNNNN ===
    hytte = hyttemappe(undermappe)
    if hytte:
        return MEDLEMMER / hytte / normaliser_hyttefil(filnavn), f"Hytte {hytte}"

    # === GAMLE MEDLEMSLISTER OG NESTET ARKIV -> X00 Historikk (flatt) ===
    if undermappe in ("Gamle medlemslister", "Arkiv"):
        nytt_navn = normaliser_filnavn(fiks_medlemsliste_dato(filnavn))
        return HISTORIKK / nytt_navn, "Medlemslister (historikk)"

    # === NYE HYTTEEIERE -> 051 Eierskifte ===
    if undermappe == "Nye hytteeiere":
        return EIERSKIFTE / normaliser_filnavn(filnavn), "Eierskifte"

    # === LØSE FILER RETT I 240 MEDLEMMER ===

    # Gjeldende rutine -> rot i 050 Medlemmer
    if filnavn_lower.startswith("rutiner for medlemsregister"):
        return MEDLEMMER / filnavn, "Medlemmer (rutiner)"

    # Overdragelsesskjemaer (mal) -> 051 Eierskifte
    if filnavn_lower.startswith("overdragelse av festerett"):
        return EIERSKIFTE / normaliser_filnavn(filnavn), "Eierskifte"

    # Tomteinnløsning: kravskjemaer og tomtedelingsplan
    if "kravskjema" in filnavn_lower or "tomtedelingsplan" in filnavn_lower:
        return TOMTEINNLØSNING / normaliser_filnavn(filnavn), "Tomteinnløsning"

    # Medlems-/maillister -> X00 Historikk (gjeldende liste er gsheet i 050 Medlemmer)
    if any(x in filnavn_lower for x in ["medlemsliste", "mailliste", "adresser til medlemmer"]):
        return HISTORIKK / normaliser_filnavn(filnavn), "Medlemslister (historikk)"

    return None  # Krever manuell håndtering


def planlegg_flyttinger() -> tuple[list[Flytting], list[Path]]:
    """Planlegger flyttingene og samler filer som ikke fikk et mål."""
    flyttinger = []
    uplasserte = []

    for mappenavn in KILDEMAPPER:
        kildemappe = KILDE / mappenavn
        if not kildemappe.exists():
            print(f"⚠️  Mappe finnes ikke: {kildemappe}")
            continue

        for fil in samle_filer(kildemappe):
            relativ = fil.relative_to(KILDE)
            resultat = bestem_målmappe(fil, relativ)

            if resultat:
                målsti, kategori = resultat
                flyttinger.append(Flytting(kilde=fil, mål=målsti, kategori=kategori))
            else:
                uplasserte.append(fil)

    return flyttinger, uplasserte


def main():
    parser = argparse.ArgumentParser(
        description="Migrerer medlems- og hytteinformasjon fra 900 Arkiv"
    )
    parser.add_argument(
        "--dry-run", "-n",
        action="store_true",
        help="Vis hva som vil skje uten å flytte filer"
    )
    parser.add_argument(
        "--csv",
        type=Path,
        help="Eksporter flyttinger til CSV-fil (kan kombineres med --dry-run)"
    )

    args = parser.parse_args()

    print(f"Kilde: {KILDE} ({', '.join(KILDEMAPPER)})")
    print(f"Mål:   {MEDLEMMER.name}, {EIERSKIFTE.name}, {TOMTEINNLØSNING.name}")

    if not KILDE.exists():
        print(f"❌ Kildemappe finnes ikke: {KILDE}")
        return 1

    flyttinger, uplasserte = planlegg_flyttinger()

    if not flyttinger:
        print("\n⚠️  Ingen filer å flytte")
        return 0

    if args.csv:
        eksporter_til_csv(flyttinger, args.csv)

    if not args.csv or args.dry_run:
        utfør_flyttinger(flyttinger, dry_run=args.dry_run)

    if uplasserte:
        print(f"⚠️  {len(uplasserte)} filer krever manuell håndtering (flyttes ikke):")
        for fil in sorted(uplasserte):
            print(f"   - {fil.relative_to(KILDE)}")
        print()

    return 0


if __name__ == "__main__":
    exit(main())
