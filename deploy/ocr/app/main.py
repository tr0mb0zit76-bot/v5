"""Minimal OCR HTTP sidecar for CRM order intake (Tesseract + OCRmyPDF)."""

from __future__ import annotations

import base64
import shutil
import subprocess
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="crm-ocr-service", version="0.2.0")

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


def optimize_pdf_bytes(content: bytes) -> tuple[bytes | None, str, list[str]]:
    """Сжатие PDF без OCR (ocrmypdf --skip-text, fallback ghostscript)."""
    warnings: list[str] = []

    with tempfile.TemporaryDirectory() as tmp:
        source = Path(tmp) / "input.pdf"
        source.write_bytes(content)
        optimized = Path(tmp) / "optimized.pdf"

        optimize_levels: list[str] = ["3", "1"] if shutil.which("pngquant") else ["1"]

        for level in optimize_levels:
            result = run_command(
                [
                    "ocrmypdf",
                    "--skip-text",
                    "--optimize",
                    level,
                    str(source),
                    str(optimized),
                ]
            )

            if result.returncode == 0 and optimized.exists() and optimized.stat().st_size > 0:
                method = "ocrmypdf" if level == "1" else f"ocrmypdf-{level}"

                return optimized.read_bytes(), method, warnings

            if result.stderr:
                warnings.append((result.stderr or "").strip()[:500])

        gs_out = Path(tmp) / "gs.pdf"
        gs = run_command(
            [
                "gs",
                "-sDEVICE=pdfwrite",
                "-dCompatibilityLevel=1.4",
                "-dPDFSETTINGS=/ebook",
                "-dNOPAUSE",
                "-dBATCH",
                "-dQUIET",
                f"-sOutputFile={gs_out}",
                str(source),
            ]
        )

        if gs.returncode == 0 and gs_out.exists() and gs_out.stat().st_size > 0:
            if gs.stderr:
                warnings.append((gs.stderr or "").strip()[:300])
            return gs_out.read_bytes(), "ghostscript", warnings

        if gs.stderr:
            warnings.append((gs.stderr or "").strip()[:500])

        warnings.append("Не удалось оптимизировать PDF")

        return None, "failed", warnings


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


@app.post("/optimize")
async def optimize(file: UploadFile = File(...)) -> JSONResponse:
    suffix = Path(file.filename or "upload").suffix.lower()

    if suffix != PDF_EXTENSION:
        return JSONResponse(
            status_code=422,
            content={
                "error": "unsupported_format",
                "message": "Оптимизация доступна только для PDF.",
            },
        )

    content = await file.read()
    if not content:
        return JSONResponse(
            status_code=422,
            content={"error": "empty_file", "message": "Пустой файл."},
        )

    optimized, method, warnings = optimize_pdf_bytes(content)

    if optimized is None:
        return JSONResponse(
            status_code=422,
            content={
                "error": "optimize_failed",
                "method": method,
                "warnings": warnings,
                "message": "Не удалось уменьшить PDF. Подготовьте файл вручную.",
            },
        )

    return JSONResponse(
        content={
            "pdf_base64": base64.b64encode(optimized).decode("ascii"),
            "original_bytes": len(content),
            "optimized_bytes": len(optimized),
            "method": method,
            "warnings": warnings,
        }
    )
