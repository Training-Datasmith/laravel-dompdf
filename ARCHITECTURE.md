# Architecture: laravel-dompdf

## Purpose
A Laravel wrapper around the dompdf library. Provides a Laravel-aware PDF generation service that renders Blade views (or arbitrary HTML strings) to PDF documents.

## Directory Structure
```
src/
  PDF.php              # Core service class — loadView(), loadHTML(), download(), stream(), save()
  Facade/Pdf.php       # Laravel facade for static access: PDF::loadView(...)
  Service_Provider.php # Registers the PDF service, merges config
config/
  dompdf.php           # Configuration: paper size, orientation, dpi, font options
```

## Key Design Decisions
- **Blade integration** — `loadView($view, $data)` renders a Blade template and passes the HTML string to dompdf, making it natural to design PDFs using existing templating skills.
- **Thin wrapper** — the package adds no rendering logic; all PDF generation is delegated to dompdf. The value is in Laravel-idiomatic configuration and service binding.
- **Response helpers** — `download($filename)`, `stream($filename)`, and `output()` return `Illuminate\Http\Response` objects with correct headers, fitting seamlessly into Laravel controllers.
- **Config merging** — `config/dompdf.php` exposes all dompdf options in a single array merged at boot, avoiding the need to manipulate dompdf's own Options object directly.

## Extension Points
- Override `config/dompdf.php` options via `config/dompdf.php` in the application.
- Use `getPdf()` to get the raw Dompdf instance for advanced manipulation (e.g., adding metadata, adding pages).
- Extend the `PDF` class and rebind it in the service container for additional functionality.

## Dependency Flow
```
PDF::loadView('invoice', ['order' => $order])
  └─ Blade::render('invoice', ['order' => $order]) → HTML string
       └─ Dompdf::loadHtml($html)
            └─ Dompdf::render() → PDF binary

PDF::download('invoice.pdf')
  └─ Response with Content-Disposition: attachment
       └─ Content-Type: application/pdf
```
