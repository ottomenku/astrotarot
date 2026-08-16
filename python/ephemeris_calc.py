#!/usr/bin/env python3
"""Minimal Swiss Ephemeris calculator for object positions + fixed-star conjunctions."""

from __future__ import annotations

import json
import sys
from datetime import datetime, timezone
from pathlib import Path

import swisseph as swe

EPHE_PATH = str(Path(__file__).resolve().parent / "ephe")
swe.set_ephe_path(EPHE_PATH)

PLANETS = [
    (swe.SUN, "Sun"),
    (swe.MOON, "Moon"),
    (swe.MERCURY, "Mercury"),
    (swe.VENUS, "Venus"),
    (swe.MARS, "Mars"),
    (swe.JUPITER, "Jupiter"),
    (swe.SATURN, "Saturn"),
    (swe.URANUS, "Uranus"),
    (swe.NEPTUNE, "Neptune"),
    (swe.PLUTO, "Pluto"),
    (swe.TRUE_NODE, "True Node"),
    (swe.MEAN_APOG, "Mean Lilith"),
]

SIGNS = [
    "Aries",
    "Taurus",
    "Gemini",
    "Cancer",
    "Leo",
    "Virgo",
    "Libra",
    "Scorpio",
    "Sagittarius",
    "Capricorn",
    "Aquarius",
    "Pisces",
]

# Swiss Ephemeris star catalog names
FIXED_STARS = [
    "Aldebaran",
    "Regulus",
    "Spica",
    "Antares",
    "Fomalhaut",
    "Sirius",
    "Algol",
    "Alcyone",
    "Castor",
    "Pollux",
    "Procyon",
    "Betelgeuse",
    "Rigel",
    "Bellatrix",
    "Alnilam",
    "Alphard",
    "Vega",
    "Deneb",
    "Altair",
    "Capella",
]

DEFAULT_CONJUNCTION_ORB = 1.0


def parse_datetime_utc(value: str) -> datetime:
    dt = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(timezone.utc)


def julian_day(dt: datetime) -> float:
    return swe.julday(
        dt.year,
        dt.month,
        dt.day,
        dt.hour + dt.minute / 60 + dt.second / 3600,
    )


def normalize_degree(deg: float) -> float:
    return deg % 360.0


def zodiac_sign(deg: float) -> tuple[str, float]:
    deg = normalize_degree(deg)
    sign_index = int(deg // 30)
    return SIGNS[sign_index], deg % 30


def angle_delta(a: float, b: float) -> float:
    d = abs(normalize_degree(a) - normalize_degree(b))
    return min(d, 360.0 - d)


def calc_ayanamsa(jd: float, sidereal: bool) -> float:
    if not sidereal:
        return 0.0
    return swe.get_ayanamsa(jd)


def object_row(
    name: str,
    longitude: float,
    kind: str,
    *,
    fixed_star: str | None = None,
    retrograde: bool = False,
    house: int | None = None,
) -> dict:
    sign, sign_degree = zodiac_sign(longitude)
    row = {
        "name": name,
        "kind": kind,
        "longitude": round(longitude, 6),
        "sign": sign,
        "degree": round(sign_degree, 4),
        "fixed_star": fixed_star,
        "retrograde": bool(retrograde),
    }
    if house is not None:
        row["house"] = int(house)
    return row


def house_number(longitude: float, cusps) -> int:
    """Return Placidus house 1..12. pyswisseph returns 12 cusps at indices 0..11."""
    cusp_list = list(cusps)
    if len(cusp_list) >= 13:
        cusp_list = [cusp_list[i] for i in range(1, 13)]
    else:
        cusp_list = cusp_list[:12]

    lon = normalize_degree(longitude)
    for i in range(12):
        start = normalize_degree(cusp_list[i])
        end = normalize_degree(cusp_list[(i + 1) % 12])
        if start <= end:
            if start <= lon < end:
                return i + 1
        elif lon >= start or lon < end:
            return i + 1
    return 1


def calc_planets(jd: float, ayanamsa: float, cusps) -> list[dict]:
    flags = swe.FLG_SWIEPH | swe.FLG_MOSEPH | swe.FLG_SPEED
    results = []
    for planet_id, name in PLANETS:
        xx = swe.calc_ut(jd, planet_id, flags)[0]
        lon = normalize_degree(xx[0] - ayanamsa)
        speed = xx[3]
        results.append(
            object_row(
                name,
                lon,
                "planet",
                retrograde=speed < 0,
                house=house_number(lon, cusps),
            )
        )
    return results


def calc_special_points(jd: float, lat: float, lon: float, ayanamsa: float, cusps, ascmc) -> list[dict]:
    asc = normalize_degree(ascmc[0] - ayanamsa)
    mc = normalize_degree(ascmc[1] - ayanamsa)
    return [
        object_row("ASC", asc, "point", house=house_number(asc, cusps)),
        object_row("MC", mc, "point", house=house_number(mc, cusps)),
    ]


def calc_fixed_stars(jd: float, ayanamsa: float) -> list[dict]:
    results = []
    for star_name in FIXED_STARS:
        try:
            result = swe.fixstar(star_name, jd)
            xx = result[0]
            retnam = result[1] if len(result) > 1 else star_name
            lon = normalize_degree(xx[0] - ayanamsa)
            label = retnam.split(",")[0].strip() if isinstance(retnam, str) and retnam else star_name
            results.append(object_row(label, lon, "fixed_star"))
        except Exception:
            continue
    return results


def attach_conjunctions(objects: list[dict], stars: list[dict], orb: float) -> None:
    for obj in objects:
        if obj["kind"] == "fixed_star":
            continue
        best_name = None
        best_delta = orb + 1
        for star in stars:
            delta = angle_delta(obj["longitude"], star["longitude"])
            if delta <= orb and delta < best_delta:
                best_delta = delta
                best_name = star["name"]
        obj["fixed_star"] = best_name


def build_ephemeris(payload: dict) -> dict:
    dt = parse_datetime_utc(payload["datetime_utc"])
    lat = float(payload.get("lat", 47.4979))
    lon = float(payload.get("lon", 19.0402))
    sidereal = bool(payload.get("sidereal", False))
    ayanamsa_name = payload.get("ayanamsa", "lahiri")
    orb = float(payload.get("conjunction_orb", DEFAULT_CONJUNCTION_ORB))

    if sidereal:
        swe.set_sid_mode(swe.SIDM_LAHIRI)

    jd = julian_day(dt)
    ayanamsa = calc_ayanamsa(jd, sidereal)
    cusps, ascmc = swe.houses(jd, lat, lon, b"P")

    planets = calc_planets(jd, ayanamsa, cusps)
    points = calc_special_points(jd, lat, lon, ayanamsa, cusps, ascmc)
    stars = calc_fixed_stars(jd, ayanamsa)

    # South Node derived from True Node
    true_node = next((p for p in planets if p["name"] == "True Node"), None)
    if true_node is not None:
        south = normalize_degree(true_node["longitude"] + 180.0)
        points.append(object_row("South Node", south, "point", house=house_number(south, cusps)))

    chart_objects = planets + points
    attach_conjunctions(chart_objects, stars, orb)

    objects = chart_objects + stars

    return {
        "datetime_utc": dt.isoformat(),
        "lat": lat,
        "lon": lon,
        "sidereal": sidereal,
        "ayanamsa": ayanamsa_name if sidereal else None,
        "ayanamsa_degree": round(ayanamsa, 6),
        "conjunction_orb": orb,
        "objects": objects,
    }


def main() -> None:
    payload = json.loads(sys.stdin.read())
    sys.stdout.write(json.dumps(build_ephemeris(payload)))


if __name__ == "__main__":
    main()
