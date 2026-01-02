#!/usr/bin/env python3
"""
Migrering av Bleikøya Vel-arkiv fra Dropbox til Google Drive.

Kjør med:
    uv run documents/migrate_archive.py --dry-run    # Vis hva som vil skje
    uv run documents/migrate_archive.py              # Utfør flytting
"""
# /// script
# requires-python = ">=3.11"
# ///

import argparse
import csv
import hashlib
import re
import shutil
import unicodedata
from dataclasses import dataclass
from pathlib import Path

# === KONFIGURASJON ===

DRIVE = Path.home() / "Library/CloudStorage/GoogleDrive-oystein.rg@gmail.com/Delte disker/Bleikøya Vel"
KILDE = DRIVE / "900 Arkiv"
MÅL = DRIVE

# Filer/mapper som skal ignoreres
IGNORER = {".DS_Store", "Icon\r", "Icon", ".dropbox"}


@dataclass
class Flytting:
    """Representerer en planlagt filflytting."""
    kilde: Path
    mål: Path
    kategori: str
    duplikat_av: Path | None = None  # Hvis dette er en duplikat, peker til original
    er_identisk: bool | None = None  # True hvis innholdet er likt


def fil_hash(path: Path) -> str:
    """Beregner SHA-256 hash av en fil."""
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()


def finn_duplikater(flyttinger: list[Flytting]) -> list[Flytting]:
    """Identifiserer duplikater og sjekker om de har likt innhold."""
    # Grupper etter målsti
    mål_til_kilder: dict[Path, list[Flytting]] = {}
    for f in flyttinger:
        mål_til_kilder.setdefault(f.mål, []).append(f)

    # Sjekk duplikater
    resultat = []
    for mål, kilder in mål_til_kilder.items():
        if len(kilder) == 1:
            resultat.append(kilder[0])
        else:
            # Flere kilder til samme mål - sjekk hash
            original = kilder[0]
            original_hash = fil_hash(original.kilde)
            resultat.append(original)

            for duplikat in kilder[1:]:
                duplikat_hash = fil_hash(duplikat.kilde)
                er_identisk = duplikat_hash == original_hash
                duplikat.duplikat_av = original.kilde
                duplikat.er_identisk = er_identisk
                resultat.append(duplikat)

    return resultat


def ekstraher_og_prefiks_dato(navn: str) -> str:
    """
    Finner dato i filnavnet og legger den til som prefiks.
    Beholder originalt innhold, men unngår dobbel dato hvis den allerede er på starten.
    Støtter formater: YYYY-MM-DD, YYYYMMDD, DD.MM.YYYY, YYYY
    Ignorerer årstallsområder som 2017-2018.
    """
    stem, ext = navn.rsplit(".", 1) if "." in navn else (navn, "")

    # Allerede har dato-prefiks? Bare normaliser formatet
    if re.match(r"^\d{4}[-\s]\d{2}[-\s]\d{2}", stem):
        # Normaliser til YYYY-MM-DD
        m = re.match(r"^(\d{4})[-\s](\d{2})[-\s](\d{2})(.*)$", stem)
        if m:
            stem = f"{m.group(1)}-{m.group(2)}-{m.group(3)}{m.group(4)}"
        return f"{stem}.{ext}" if ext else stem

    # Allerede har årstall-prefiks? Behold som det er
    if re.match(r"^\d{4}\s", stem):
        return f"{stem}.{ext}" if ext else stem

    dato = None

    # YYYYMMDD (uten bindestrek, f.eks. 20180528)
    m = re.search(r"(?<!\d)(\d{4})(\d{2})(\d{2})(?!\d)", stem)
    if m:
        år, mnd, dag = m.groups()
        if 1 <= int(mnd) <= 12 and 1 <= int(dag) <= 31:
            dato = f"{år}-{mnd}-{dag}"

    # DD.MM.YYYY eller DD.MM.YY
    if not dato:
        m = re.search(r"(\d{1,2})\.(\d{1,2})\.(\d{2,4})", stem)
        if m:
            dag, mnd, år = m.groups()
            if len(år) == 2:
                år = "20" + år
            if 1 <= int(mnd) <= 12 and 1 <= int(dag) <= 31:
                dato = f"{år}-{mnd.zfill(2)}-{dag.zfill(2)}"

    # YYYY-MM-DD (med bindestrek, men ikke YYYY-YYYY årstallsområde)
    if not dato:
        m = re.search(r"(\d{4})-(\d{2})-(\d{2})", stem)
        if m:
            år, mnd, dag = m.groups()
            if 1 <= int(mnd) <= 12 and 1 <= int(dag) <= 31:
                dato = f"{år}-{mnd}-{dag}"

    # Bare YYYY (årstall alene, men IKKE del av YYYY-YYYY område)
    if not dato:
        for m in re.finditer(r"(?<!\d)(\d{4})(?!\d)", stem):
            år = m.group(1)
            if 2000 <= int(år) <= 2030:
                # Sjekk om det er del av et YYYY-YYYY område
                før = stem[max(0, m.start()-1):m.start()]
                etter = stem[m.end():m.end()+5]
                if før == "-" and re.match(r"\d{4}", stem[m.start()-5:m.start()-1] if m.start() >= 5 else ""):
                    continue  # Slutten av et område (f.eks. "-2018" i "2017-2018")
                if etter.startswith("-") and re.match(r"\d{4}", etter[1:5]):
                    continue  # Starten av et område (f.eks. "2017-" i "2017-2018")
                dato = år
                break

    if dato:
        # Legg til dato som prefiks, behold resten av filnavnet uendret
        stem = f"{dato} {stem}"

    return f"{stem}.{ext}" if ext else stem


def normaliser_filnavn(navn: str) -> str:
    """Fjern unødvendige tegn og normaliser filnavn."""
    # Fjern usynlige tegn
    navn = navn.replace("\u2060", "").replace("\u200b", "")
    navn = navn.strip()

    # Ekstraher dato og prefiks den
    navn = ekstraher_og_prefiks_dato(navn)

    # Konverter UPPERCASE til Sentence case (norsk-vennlig)
    stem, ext = navn.rsplit(".", 1) if "." in navn else (navn, "")

    # Fjern eventuell dato-prefix for å sjekke teksten
    tekst = re.sub(r"^\d{4}(-\d{2}){0,2}\s+", "", stem)

    # Hvis teksten er hovedsakelig UPPERCASE (mer enn 70% store bokstaver)
    bokstaver = [c for c in tekst if c.isalpha()]
    if bokstaver and sum(1 for c in bokstaver if c.isupper()) / len(bokstaver) > 0.7:
        # Behold dato-prefix, konverter resten til sentence case
        dato_match = re.match(r"^(\d{4}(?:-\d{2}){0,2}\s+)?(.+)$", stem)
        if dato_match:
            prefix = dato_match.group(1) or ""
            tekst = dato_match.group(2).capitalize()
            stem = prefix + tekst
        navn = f"{stem}.{ext}" if ext else stem

    return navn


def ekstraher_dato(filnavn: str) -> str | None:
    """Prøv å ekstrahere dato fra filnavn og returner YYYY-MM-DD format."""
    # Mønster: YYYY-MM-DD, YYYY MM DD, YYYYMMDD
    m = re.search(r"(\d{4})[-\s_]?(\d{2})[-\s_]?(\d{2})", filnavn)
    if m:
        return f"{m.group(1)}-{m.group(2)}-{m.group(3)}"

    # Mønster: DD.MM.YYYY eller DD.MM.YY
    m = re.search(r"(\d{1,2})\.(\d{1,2})\.(\d{2,4})", filnavn)
    if m:
        dag, mnd, år = m.groups()
        if len(år) == 2:
            år = "20" + år
        return f"{år}-{mnd.zfill(2)}-{dag.zfill(2)}"

    return None


def bestem_målmappe(kilde: Path, relativ_sti: Path) -> tuple[Path, str] | None:
    """
    Bestemmer målmappe basert på kildefil.
    Returnerer (målsti, kategori) eller None hvis filen skal hoppes over.
    """
    # Normaliser Unicode (macOS bruker ofte NFD, Python-strenger er NFC)
    filnavn = unicodedata.normalize("NFC", kilde.name)
    mappenavn = unicodedata.normalize("NFC", relativ_sti.parts[0]) if relativ_sti.parts else ""
    undermapper = tuple(unicodedata.normalize("NFC", p) for p in relativ_sti.parts[1:-1]) if len(relativ_sti.parts) > 1 else ()

    filnavn_lower = filnavn.lower()

    # === MVA/FRIVILLIGHETSREGISTERET -> 500-prosjekt (tidlig for å fange alle filer) ===
    if "mva" in mappenavn.lower() or "frivillighetsregister" in mappenavn.lower():
        understi = Path(*relativ_sti.parts[1:-1]) if len(relativ_sti.parts) > 1 else Path()
        return MÅL / "502 2024 MVA-refusjon" / understi / normaliser_filnavn(filnavn), "MVA-refusjon"

    # === STYREMØTER -> 021 Styremøter (flatt med møtemapper) ===
    if "styrereferat" in filnavn_lower or "referat styremøte" in filnavn_lower:
        dato = ekstraher_dato(filnavn)
        if dato:
            # Formater dato som "4. november 2025" for lesbarhet
            år, mnd, dag = dato.split("-")
            måneder = ["januar", "februar", "mars", "april", "mai", "juni",
                      "juli", "august", "september", "oktober", "november", "desember"]
            dato_lesbar = f"{int(dag)}. {måneder[int(mnd)-1]} {år}"

            # Mappe: 2025-11-04 Styremøte 4. november 2025
            møtemappe = f"{dato} Styremøte {dato_lesbar}"
            # Fil: normaliser_filnavn håndterer dato-prefiks
            return MÅL / "021 Styremøter" / møtemappe / normaliser_filnavn(filnavn), "Styrereferat"
        else:
            # Fallback hvis ingen dato funnet
            return MÅL / "021 Styremøter" / "_usortert" / normaliser_filnavn(filnavn), "Styrereferat"

    # === GENERALFORSAMLING -> 010 Generalforsamling ===
    if any(x in filnavn_lower for x in ["generalforsamling", "protokoll gf", "protokoll fra gf"]):
        år = None
        for part in list(undermapper) + [mappenavn, filnavn]:
            m = re.search(r"20\d{2}", part)
            if m:
                år = m.group()
                break

        if år:
            return MÅL / "010 Generalforsamling" / f"{år} Generalforsamling" / normaliser_filnavn(filnavn), "Generalforsamling"

    # === MEDLEMSMØTE -> 010 Generalforsamling ===
    if "medlemsmøte" in filnavn_lower:
        år = None
        for part in list(undermapper) + [mappenavn, filnavn]:
            m = re.search(r"20\d{2}", part)
            if m:
                år = m.group()
                break

        if år:
            return MÅL / "010 Generalforsamling" / f"{år} Generalforsamling" / normaliser_filnavn(filnavn), "Medlemsmøte"

    # === INNKALLING -> 010 Generalforsamling ===
    if "innkalling" in filnavn_lower and "general" in filnavn_lower:
        år = None
        for part in list(undermapper) + [mappenavn, filnavn]:
            m = re.search(r"20\d{2}", part)
            if m:
                år = m.group()
                break

        if år:
            return MÅL / "010 Generalforsamling" / f"{år} Generalforsamling" / normaliser_filnavn(filnavn), "Innkalling GF"

    # === ÅRSBERETNING -> 010 Generalforsamling (årsberetning for år X -> GF X+1) ===
    if "årsberetning" in filnavn_lower:
        år = None
        m = re.search(r"20\d{2}", filnavn)
        if m:
            år = m.group()
        if år:
            gf_år = str(int(år) + 1)  # Årsberetning 2023 -> GF 2024
            return MÅL / "010 Generalforsamling" / f"{gf_år} Generalforsamling" / normaliser_filnavn(filnavn), "Årsberetning"

    # === FELLESSTYRET -> 090 Fellesstyret ===
    if "fellesstyret" in filnavn_lower or "fellestyret" in filnavn_lower or "fellesstyret" in mappenavn.lower():
        nytt_navn = normaliser_filnavn(filnavn)
        # Behold undermappe-struktur hvis relevant
        if "fellesstyret" in mappenavn.lower() and len(undermapper) > 0:
            undermappe = undermapper[0]
            return MÅL / "090 Fellesstyret" / undermappe / nytt_navn, "Fellesstyret"
        return MÅL / "090 Fellesstyret" / nytt_navn, "Fellesstyret"

    # === SAKSLISTE / VALGKOMITE -> 010 Generalforsamling ===
    if any(x in filnavn_lower for x in ["saksliste", "valgkomite"]):
        år = None
        for part in list(undermapper) + [mappenavn, filnavn]:
            m = re.search(r"20\d{2}", part)
            if m:
                år = m.group()
                break

        if år:
            return MÅL / "010 Generalforsamling" / f"{år} Generalforsamling" / normaliser_filnavn(filnavn), "GF-vedlegg"

    # === EKSTRAORDINÆR GF -> 010 Generalforsamling/Ekstraordinære ===
    if "ekstraordinær" in mappenavn.lower() or "ex.ord" in filnavn_lower:
        # Finn årstall for mappe
        år = None
        for part in list(undermapper) + [mappenavn, filnavn]:
            m = re.search(r"20\d{2}", part)
            if m:
                år = m.group()
                break
        if år:
            return MÅL / "010 Generalforsamling" / f"{år} Generalforsamling" / "Ekstraordinær" / normaliser_filnavn(filnavn), "Ekstraordinær GF"

    # === STATSBYGG REFERAT -> 310 Statsbygg ===
    if "statsbygg" in filnavn_lower and ("referat" in filnavn_lower or "rapport" in filnavn_lower):
        return MÅL / "310 Statsbygg" / normaliser_filnavn(filnavn), "Statsbygg"

    # === VEDTEKTER -> 000 Vedtekter og styringsdokumenter ===
    if "vedtekter" in filnavn_lower:
        # Eldre versjoner (med dato eller "revidert", "gammel") -> X00 Historikk
        har_dato = bool(re.search(r"20\d{2}", filnavn))
        er_historisk = any(x in filnavn_lower for x in ["revidert", "gammel", "utgått", "tidligere"])

        if har_dato or er_historisk:
            return MÅL / "000 Vedtekter og styringsdokumenter" / "X00 Historikk" / normaliser_filnavn(filnavn), "Vedtekter (historikk)"
        else:
            return MÅL / "000 Vedtekter og styringsdokumenter" / normaliser_filnavn(filnavn), "Vedtekter"

    # === REGNSKAP/BUDSJETT -> 010 Generalforsamling (regnskap for år X -> GF X+1) ===
    if any(x in filnavn_lower for x in ["regnskap", "budsjett"]) and kilde.suffix.lower() in [".xlsx", ".xls", ".pdf", ".docx", ".doc"]:
        år = None
        m = re.search(r"20\d{2}", filnavn)
        if m:
            år = m.group()
        if år:
            gf_år = str(int(år) + 1)  # Regnskap 2023 -> GF 2024
            return MÅL / "010 Generalforsamling" / f"{gf_år} Generalforsamling" / normaliser_filnavn(filnavn), "Regnskap/Budsjett"

    # === VÅRBREV -> 024 Vårbrev og medlemskommunikasjon ===
    if "vårbrev" in filnavn_lower:
        år = None
        m = re.search(r"20\d{2}", filnavn)
        if m:
            år = m.group()
        if år:
            return MÅL / "024 Vårbrev og medlemskommunikasjon" / normaliser_filnavn(filnavn), "Vårbrev"

    # === AVTALER -> 070 Avtaler og instruks ===
    if filnavn_lower.startswith("avtale ") or filnavn_lower.startswith("kontrakt "):
        return MÅL / "070 Avtaler og instruks" / normaliser_filnavn(filnavn), "Avtaler"

    # === INSTRUKSER -> 070 Avtaler og instruks ===
    if filnavn_lower.startswith("instruks "):
        return MÅL / "070 Avtaler og instruks" / normaliser_filnavn(filnavn), "Instrukser"

    # === TOMTEINNLØSNING -> 500 2020-2024 Tomteinnløsning (behold mappestruktur) ===
    if mappenavn == "Tomteinnløsning":
        # Behold undermapper fra kilden
        understi = Path(*relativ_sti.parts[1:-1]) if len(relativ_sti.parts) > 1 else Path()
        return MÅL / "500 2020-2024 Tomteinnløsning" / understi / normaliser_filnavn(filnavn), "Tomteinnløsning"

    # === ULOVLIGHETSOPPFØLGING PBE -> 500 2020-2025 Ulovlighetsoppfølging brygger (behold mappestruktur) ===
    if "ulovlighetsoppfølging" in mappenavn.lower():
        understi = Path(*relativ_sti.parts[1:-1]) if len(relativ_sti.parts) > 1 else Path()
        return MÅL / "500 2020-2025 Ulovlighetsoppfølging brygger" / understi / normaliser_filnavn(filnavn), "Ulovlighetsoppfølging"

    # Vann og kloakk / Strømnettet: Ikke flyttes automatisk - håndteres manuelt

    # Bål søknad: Ikke flyttes automatisk - håndteres manuelt

    # === RENOVASJON -> 230 Renovasjon ===
    if mappenavn == "Renovasjon":
        return MÅL / "230 Renovasjon" / normaliser_filnavn(filnavn), "Renovasjon"

    # === SKJØTSEL/DUGNAD -> 070 Avtaler og instruks (regler) eller 250 Skjøtsel ===
    if "skjøtsel" in mappenavn.lower():
        if any(x in filnavn_lower for x in ["regler", "instruks", "flytdiagram", "sjekkliste"]):
            return MÅL / "070 Avtaler og instruks" / normaliser_filnavn(filnavn), "Skjøtsel (regler)"
        else:
            return MÅL / "250 Skjøtsel og miljø" / normaliser_filnavn(filnavn), "Skjøtsel"

    # === ANBUD VAKTMESTERHYTTA -> 500 2016 Renovering vaktmesterhytta (behold mappestruktur) ===
    if "anbud" in mappenavn.lower() and "vaktmester" in str(relativ_sti).lower():
        understi = Path(*relativ_sti.parts[1:-1]) if len(relativ_sti.parts) > 1 else Path()
        return MÅL / "500 2016 Renovering vaktmesterhytta" / understi / normaliser_filnavn(filnavn), "Vaktmesterhytta"

    return None  # Filen sorteres ikke (ennå)


def samle_filer(mappe: Path) -> list[Path]:
    """Samler alle filer rekursivt fra en mappe."""
    filer = []
    for fil in mappe.rglob("*"):
        if fil.is_file() and fil.name not in IGNORER and not fil.name.startswith("~$"):
            filer.append(fil)
    return filer


def planlegg_flyttinger(mapper: list[str]) -> list[Flytting]:
    """Planlegger alle flyttinger fra de angitte mappene."""
    flyttinger = []

    for mappenavn in mapper:
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

    return flyttinger


def eksporter_til_csv(flyttinger: list[Flytting], csv_fil: Path) -> None:
    """Eksporterer flyttingene til en CSV-fil."""
    with open(csv_fil, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["Kategori", "Kilde", "Mål", "Kildefil", "Målfil"])

        for fl in sorted(flyttinger, key=lambda x: (x.kategori, x.mål)):
            kilde_relativ = fl.kilde.relative_to(KILDE)
            mål_relativ = fl.mål.relative_to(MÅL)
            writer.writerow([
                fl.kategori,
                str(kilde_relativ.parent),
                str(mål_relativ.parent),
                fl.kilde.name,
                fl.mål.name,
            ])

    print(f"✅ Eksportert {len(flyttinger)} filer til {csv_fil}")


def utfør_flyttinger(flyttinger: list[Flytting], dry_run: bool = True) -> None:
    """Utfører eller simulerer flyttingene."""

    # Sjekk for duplikater
    flyttinger = finn_duplikater(flyttinger)

    # Grupper etter kategori for oversiktlig output
    kategorier: dict[str, list[Flytting]] = {}
    for f in flyttinger:
        kategorier.setdefault(f.kategori, []).append(f)

    total = len(flyttinger)
    utført = 0
    duplikater_hoppet = 0
    duplikater_ulike = 0

    print(f"\n{'='*60}")
    print(f"{'DRY RUN - Ingen filer flyttes' if dry_run else 'UTFØRER FLYTTING'}")
    print(f"{'='*60}\n")

    for kategori, filer in sorted(kategorier.items()):
        print(f"\n## {kategori} ({len(filer)} filer)\n")

        for f in sorted(filer, key=lambda x: x.mål):
            kilde_kort = f.kilde.relative_to(KILDE)
            mål_kort = f.mål.relative_to(MÅL)

            # Håndter duplikater
            if f.duplikat_av is not None:
                if f.er_identisk:
                    print(f"  ⏭️  DUPLIKAT (identisk): {kilde_kort}")
                    print(f"     = {f.duplikat_av.relative_to(KILDE)}\n")
                    duplikater_hoppet += 1
                    continue  # Hopp over identiske duplikater
                else:
                    # Ulik fil med samme navn - legg til suffiks
                    stem = f.mål.stem
                    suffix = f.mål.suffix
                    # Bruk kildemappen som suffiks for å skille
                    kilde_mappe = f.kilde.parent.name
                    ny_mål = f.mål.parent / f"{stem} ({kilde_mappe}){suffix}"
                    print(f"  ⚠️  DUPLIKAT (ulikt innhold): {kilde_kort}")
                    print(f"     ≠ {f.duplikat_av.relative_to(KILDE)}")
                    print(f"     → Omdøpt til: {ny_mål.name}\n")
                    f.mål = ny_mål
                    duplikater_ulike += 1

            if dry_run:
                if f.duplikat_av is None:  # Vanlig fil
                    print(f"  📄 {kilde_kort}")
                    print(f"     → {mål_kort}\n")
            else:
                # Opprett målmappe hvis den ikke finnes
                f.mål.parent.mkdir(parents=True, exist_ok=True)

                # Flytt fil (innenfor samme Drive)
                if f.mål.exists():
                    print(f"  ⚠️  Finnes allerede: {mål_kort}")
                else:
                    shutil.move(f.kilde, f.mål)
                    print(f"  ✅ {kilde_kort} → {mål_kort}")
                    utført += 1

    print(f"\n{'='*60}")
    if dry_run:
        faktisk_flyttes = total - duplikater_hoppet
        print(f"Totalt: {total} filer funnet")
        if duplikater_hoppet > 0:
            print(f"  - {duplikater_hoppet} identiske duplikater hoppes over")
        if duplikater_ulike > 0:
            print(f"  - {duplikater_ulike} duplikater med ulikt innhold omdøpes")
        print(f"  = {faktisk_flyttes} filer ville blitt flyttet")
        print(f"\nKjør uten --dry-run for å utføre flyttingen")
    else:
        print(f"Flyttet: {utført}/{total} filer")
        if duplikater_hoppet > 0:
            print(f"Duplikater hoppet over: {duplikater_hoppet}")
    print(f"{'='*60}\n")


def main():
    parser = argparse.ArgumentParser(
        description="Migrerer Bleikøya Vel-arkiv fra Dropbox til Google Drive"
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
    parser.add_argument(
        "--mapper", "-m",
        nargs="+",
        default=[
            # Mapper i 900 Arkiv som skal migreres
            "Referat",
            "Generalforsamling",
            "Vårbrev, Årsberetning",
            "Avtaler og instruks",
            "Regnskap, budsjett",
            "Vedtekter, informasjon til hytteeierne",
            "Tomteinnløsning",
            "Ulovlighetsoppfølging PBE",
            "Frivillighetsregisteret MVA refusjon",
            "Renovasjon",
            "Skjøtsel, dugnad, trær, planter",
            "Anbud",
            "Fellesstyret for øyene",
        ],
        help="Hvilke mapper i 900 Arkiv som skal behandles"
    )

    args = parser.parse_args()

    print(f"Kilde: {KILDE}")
    print(f"Mål:   {MÅL}")
    print(f"Mapper: {', '.join(args.mapper)}")

    # Verifiser at stier finnes
    if not KILDE.exists():
        print(f"❌ Kildemappe finnes ikke: {KILDE}")
        return 1

    if not MÅL.exists():
        print(f"❌ Målmappe finnes ikke: {MÅL}")
        return 1

    # Planlegg og utfør
    flyttinger = planlegg_flyttinger(args.mapper)

    if not flyttinger:
        print("\n⚠️  Ingen filer å flytte")
        return 0

    # Eksporter til CSV hvis ønsket
    if args.csv:
        eksporter_til_csv(flyttinger, args.csv)

    # Vis/utfør flyttinger (med mindre bare CSV er ønsket)
    if not args.csv or args.dry_run:
        utfør_flyttinger(flyttinger, dry_run=args.dry_run)

    return 0


if __name__ == "__main__":
    exit(main())
