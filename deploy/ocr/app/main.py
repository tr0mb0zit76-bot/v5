"""Minimal OCR HTTP sidecar for CRM order intake (Tesseract + OCRmyPDF)."""

from __future__ import annotations

import shutil
import subprocess
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="crm-ocr-service", version="0.1.0")

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
PDF_EXTENSION = ".pdf"


def run_command(args: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        args,
        check=False,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )


def pdftotext(path: Path) -> str:
    result = run_command(["pdftotext", "-layout", str(path), "-"])
    if result.returncode != 0:
        return ""

    return (result.stdout or "").strip()


def ocrmypdf_then_text(source: Path) -> tuple[str, list[str]]:
    warnings: list[str] = []

    with tempfile.TemporaryDirectory() as tmp:
        ocr_pdf = Path(tmp) / "ocr.pdf"
        result = run_command(
            [
                "ocrmypdf",
                "--force-ocr",
                "--language",
                "rus+eng",
                "--optimize",
                "0",
                str(source),
                str(ocr_pdf),
            ]
        )

        if result.returncode != 0:
            stderr = (result.stderr or "").strip()
            warnings.append(stderr or "ocrmypdf завершился с ошибкой")
            return "", warnings

        text = pdftotext(ocr_pdf)
        if text == "":
            warnings.append("После OCRmyPDF текст не извлечён")

        return text, warnings


def tesseract_image(path: Path) -> tuple[str, list[str]]:
    warnings: list[str] = []
    result = run_command(["tesseract", str(path), "stdout", "-l", "rus+eng"])

    if result.returncode != 0:
        stderr = (result.stderr or "").strip()
        warnings.append(stderr or "tesseract завершился с ошибкой")
        return "", warnings

    return (result.stdout or "").strip(), warnings


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/extract")
async def extract(file: UploadFile = File(...)) -> JSONResponse:
    suffix = Path(file.filename or "upload").suffix.lower()
    warnings: list[str] = []

    with tempfile.TemporaryDirectory() as tmp:
        target = Path(tmp) / f"upload{suffix if suffix else '.bin'}"
        content = await file.read()
        target.write_bytes(content)

        if suffix == PDF_EXTENSION:
            text = pdftotext(target)
            method = "pdf_text"

            if text == "":
                text, ocr_warnings = ocrmypdf_then_text(target)
                warnings.extend(ocr_warnings)
                method = "ocrmypdf" if text else method

        elif suffix in IMAGE_EXTENSIONS:
            text, ocr_warnings = tesseract_image(target)
            warnings.extend(ocr_warnings)
            method = "tesseract"

        else:
            return JSONResponse(
                status_code=422,
                content={
                    "text": "",
                    "method": "unsupported",
                    "warnings": [f"Формат {suffix or '(без расширения)'} не поддерживается OCR-сервисом"],
                },
            )

    return JSONResponse(
        content={
            "text": text,
            "method": method,
            "warnings": warnings,
        }
    )
