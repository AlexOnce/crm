#!/usr/bin/env python3
import sys
import os
import re
import json

def parse_flight_pdf(pdf_path):
    if not os.path.exists(pdf_path):
        return {"error": f"Archivo no encontrado: {pdf_path}", "passengers": []}

    try:
        import pypdf
    except ImportError:
        return {"error": "Libreria pypdf no disponible en Python", "passengers": []}

    try:
        reader = pypdf.PdfReader(pdf_path)
    except Exception as e:
        return {"error": f"Error al abrir el PDF: {str(e)}", "passengers": []}

    full_text = ""
    for page in reader.pages:
        try:
            full_text += "\n" + page.extract_text()
        except Exception:
            pass

    # Extraer informacion de cabecera de vuelo
    flight_m = re.search(r'REPORTE\s+GENERAL\s+DEL\s+VUELO\s+([A-Z0-9-]+)', full_text, re.I)
    flight_num = flight_m.group(1).strip() if flight_m else "708"

    date_m = re.search(r'Fecha\s+de\s+Salida:\s*(\d{2}/\d{2}/\d{4})\s*(\d{2}:\d{2})?', full_text, re.I)
    departure_date_raw = date_m.group(1) if date_m else None
    departure_time = date_m.group(2) if (date_m and date_m.group(2)) else None

    departure_date = None
    if departure_date_raw:
        # Convertir DD/MM/YYYY a YYYY-MM-DD para compatibilidad con inputs date de HTML
        parts = departure_date_raw.split('/')
        if len(parts) == 3:
            departure_date = f"{parts[2]}-{parts[1]}-{parts[0]}"

    # Extraer bloques de pasajeros usando el patrón de fila de tabla:
    # Inicio de fila: Nro (1-3 digitos) + Origen (3 letras) + Destino (3 letras) + PNR (5-8 alfanumerico)
    pattern = r'(?m)^\s*(\d{1,3})\s+([A-Z]{3})\s+([A-Z]{3})\s+([A-Z0-9]{5,8})\s+'
    matches = list(re.finditer(pattern, full_text))

    passengers = []
    default_origin = "VVI"
    default_dest = "LPB"

    for i, m in enumerate(matches):
        num = int(m.group(1))
        origin = m.group(2).strip()
        dest = m.group(3).strip()
        pnr = m.group(4).strip()
        default_origin = origin
        default_dest = dest

        start_pos = m.end()
        end_pos = matches[i + 1].start() if i + 1 < len(matches) else len(full_text)
        block = full_text[start_pos:end_pos].strip()

        # Detección de Tarifa (HTAMEP-PGADT, SSENIORADT, etc.)
        tarifa_m = re.search(r'\b([A-Z0-9]*(?:TAMEP|SENIOR|SERV|WAY|BCHD)[A-Z0-9-]*)\b', block)
        if tarifa_m:
            name_raw = block[:tarifa_m.start()]
            tarifa = tarifa_m.group(1)
            rest = block[tarifa_m.end():]
        else:
            name_raw = block
            tarifa = "TAMEP-ADT"
            rest = block

        # Nombre del pasajero limpio
        name = " ".join(name_raw.split()).strip()

        # Teléfono boliviano (8 dígitos que comienzan en 6 o 7, con o sin +591 / 591)
        phone_m = re.search(r'(?:\+?591[\s-]*)?([67]\d{7})\b', rest)
        phone = phone_m.group(1) if phone_m else None

        # Email
        email_m = re.search(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', rest)
        email = email_m.group(0).strip().lower() if email_m else None

        # DNI / CI
        dni_m = re.search(r'\b(\d{5,9}[A-Z]?)\b', rest)
        dni = dni_m.group(1) if dni_m else None
        if phone and (dni == phone or dni == '591' + str(phone)):
            all_nums = re.findall(r'\b(\d{5,9}[A-Z]?)\b', rest)
            dni = all_nums[1] if len(all_nums) > 1 else None

        # Apellido paterno del pasajero
        name_parts = name.split()
        if not name_parts:
            paternal_surname = "Pasajero"
        elif name_parts[0].upper() == "DE" and len(name_parts) > 2 and name_parts[1].upper() == "LA":
            paternal_surname = f"{name_parts[0]} {name_parts[1]} {name_parts[2]}"
        elif name_parts[0].upper() in ["DE", "DEL"] and len(name_parts) > 1:
            paternal_surname = f"{name_parts[0]} {name_parts[1]}"
        else:
            paternal_surname = name_parts[0]

        passengers.append({
            "num": num,
            "pnr": pnr,
            "origin": origin,
            "destination": dest,
            "passenger_name": name,
            "paternal_surname": paternal_surname,
            "tarifa": tarifa,
            "dni": dni,
            "phone": phone,
            "email": email
        })

    valid_phones = [p for p in passengers if p.get("phone")]

    airport_cities = {
        "VVI": "Santa Cruz",
        "SRZ": "Santa Cruz",
        "LPB": "La Paz",
        "CBB": "Cochabamba",
        "TJA": "Tarija",
        "SRE": "Sucre",
        "CIJ": "Cobija",
        "TDD": "Trinidad",
        "RIB": "Riberalta",
        "GYA": "Guayaramerin",
        "RBQ": "Rurrenabaque",
        "UYU": "Uyuni",
        "BYC": "Yacuiba",
        "POI": "Potosi"
    }

    return {
        "success": True,
        "flight_number": flight_num,
        "departure_date": departure_date,
        "departure_date_raw": departure_date_raw,
        "departure_time": departure_time,
        "origin": default_origin,
        "destination": default_dest,
        "origin_city": airport_cities.get(default_origin.upper(), default_origin),
        "destination_city": airport_cities.get(default_dest.upper(), default_dest),
        "total_passengers": len(passengers),
        "valid_phones_count": len(valid_phones),
        "passengers": passengers
    }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Ruta de archivo PDF requerida"}))
        sys.exit(1)

    pdf_file = sys.argv[1]
    result = parse_flight_pdf(pdf_file)
    print(json.dumps(result, ensure_ascii=True))
