<?php

declare (strict_types=1);
namespace Barryvdh\Dom_Pdf;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Http_Foundation\Header_Utils;
/**
 * A Laravel wrapper for Dompdf
 *
 * @package laravel-dompdf
 * @author Barry vd. Heuvel
 *
 * @method PDF setBaseHost(string $baseHost)
 * @method PDF setBasePath(string $basePath)
 * @method PDF setCanvas(\Dompdf\Canvas $canvas)
 * @method PDF setCallbacks(array<string, mixed> $callbacks)
 * @method PDF setCss(\Dompdf\Css\Stylesheet $css)
 * @method PDF setDefaultView(string $defaultView, array<string, mixed> $options)
 * @method PDF setDom(\DOMDocument $dom)
 * @method PDF setFontMetrics(\Dompdf\FontMetrics $fontMetrics)
 * @method PDF setHttpContext(resource|array<string, mixed> $httpContext)
 * @method PDF setPaper(string|float[] $paper, string $orientation = 'portrait')
 * @method PDF setProtocol(string $protocol)
 * @method PDF setTree(\Dompdf\Frame\FrameTree $tree)
 * @method string getBaseHost()
 * @method string getBasePath()
 * @method \Dompdf\Canvas getCanvas()
 * @method array<string, mixed> getCallbacks()
 * @method \Dompdf\Css\Stylesheet getCss()
 * @method \DOMDocument getDom()
 * @method \Dompdf\FontMetrics getFontMetrics()
 * @method resource getHttpContext()
 * @method Options getOptions()
 * @method \Dompdf\Frame\FrameTree getTree()
 * @method string getPaperOrientation()
 * @method float[] getPaperSize()
 * @method string getProtocol()
 */
class PDF
{
    /** @var \Illuminate\Filesystem\Filesystem  */
    protected $files;
    /** @var bool */
    protected $rendered = false;
    /** @var bool */
    protected $show_warnings;
    /** @var string */
    protected $public_path;
    public function __construct(protected \Dompdf\Dompdf $dompdf, protected \Illuminate\Contracts\Config\Repository $config, Filesystem $files, protected \Illuminate\Contracts\View\Factory $view)
    {
        $this->files = $files;
        $this->show_warnings = $this->config->get('dompdf.show_warnings', false);
    }
    /**
     * Get the DomPDF instance
     */
    public function get_dom_pdf(): Dompdf
    {
        return $this->dompdf;
    }
    /**
     * Show or hide warnings
     */
    public function set_warnings(bool $warnings): self
    {
        $this->show_warnings = $warnings;
        return $this;
    }
    /**
     * Load a HTML string
     *
     * @param string|null $encoding Not used yet
     */
    public function load_html(string $string, ?string $encoding = null): self
    {
        $string = $this->convert_entities($string);
        $this->dompdf->load_html($string, $encoding);
        $this->rendered = false;
        return $this;
    }
    /**
     * Load a HTML file
     */
    public function load_file(string $file): self
    {
        $this->dompdf->load_html_file($file);
        $this->rendered = false;
        return $this;
    }
    /**
     * Add metadata info
     * @param array<string, string> $info
     */
    public function add_info(array $info): self
    {
        foreach ($info as $name => $value) {
            $this->dompdf->add_info($name, $value);
        }
        return $this;
    }
    /**
     * Load a View and convert to HTML
     * @param array<string, mixed> $data
     * @param array<string, mixed> $mergeData
     * @param string|null $encoding Not used yet
     */
    public function load_view(string $view, array $data = [], array $merge_data = [], ?string $encoding = null): self
    {
        $html = $this->view->make($view, $data, $merge_data)->render();
        return $this->load_html($html, $encoding);
    }
    /**
     * Set/Change an option (or array of options) in Dompdf
     *
     * @param array<string, mixed>|string $attribute
     * @param null|mixed $value
     */
    public function set_option($attribute, $value = null): self
    {
        $this->dompdf->get_options()->set($attribute, $value);
        return $this;
    }
    /**
     * Replace all the Options from DomPDF
     *
     * @param array<string, mixed> $options
     */
    public function set_options(array $options, bool $merge_with_defaults = false): self
    {
        if ($merge_with_defaults) {
            $options = array_merge(app()->make('dompdf.options'), $options);
        }
        $this->dompdf->set_options(new Options($options));
        return $this;
    }
    /**
     * Output the PDF as a string.
     *
     * The options parameter controls the output. Accepted options are:
     *
     * 'compress' = > 1 or 0 - apply content stream compression, this is
     *    on (1) by default
     *
     * @param array<string, int> $options
     *
     * @return string The rendered PDF as string
     */
    public function output(array $options = []): string
    {
        if (!$this->rendered) {
            $this->render();
        }
        return (string) $this->dompdf->output($options);
    }
    /**
     * Save the PDF to a file
     */
    public function save(string $filename, ?string $disk = null): self
    {
        $disk = $disk ?: $this->config->get('dompdf.disk');
        if (!is_null($disk)) {
            Storage::disk($disk)->put($filename, $this->output());
            return $this;
        }
        $this->files->put($filename, $this->output());
        return $this;
    }
    /**
     * Make the PDF downloadable by the user
     */
    public function download(string $filename = 'document.pdf'): Response
    {
        $output = $this->output();
        $fallback = $this->fallback_name($filename);
        return new Response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => Header_Utils::make_disposition('attachment', $filename, $fallback), 'Content-Length' => strlen($output)]);
    }
    /**
     * Return a response with the PDF to show in the browser
     */
    public function stream(string $filename = 'document.pdf'): Response
    {
        $output = $this->output();
        $fallback = $this->fallback_name($filename);
        return new Response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => Header_Utils::make_disposition('inline', $filename, $fallback)]);
    }
    /**
     * Render the PDF
     */
    public function render(): void
    {
        $this->dompdf->render();
        if ($this->show_warnings) {
            global $_dompdf_warnings;
            if (!empty($_dompdf_warnings) && count($_dompdf_warnings)) {
                $warnings = '';
                foreach ($_dompdf_warnings as $msg) {
                    $warnings .= $msg . "\n";
                }
                // $warnings .= $this->dompdf->get_canvas()->get_cpdf()->messages;
                if (!empty($warnings)) {
                    throw new Exception($warnings);
                }
            }
        }
        $this->rendered = true;
    }
    /** @param array<string> $pc */
    public function set_encryption(string $password, string $ownerpassword = '', array $pc = []): void
    {
        $this->render();
        $canvas = $this->dompdf->get_canvas();
        if (!$canvas instanceof CPDF) {
            throw new \RuntimeException('Encryption is only supported when using CPDF');
        }
        $canvas->get_cpdf()->set_encryption($password, $ownerpassword, $pc);
    }
    protected function convert_entities(string $subject): string
    {
        if (false === $this->config->get('dompdf.convert_entities', true)) {
            return $subject;
        }
        $entities = ['€' => '&euro;', '£' => '&pound;'];
        foreach ($entities as $search => $replace) {
            $subject = str_replace($search, $replace, $subject);
        }
        return $subject;
    }
    /**
     * Dynamically handle calls into the dompdf instance.
     *
     * @param array<mixed> $parameters
     * @return $this|mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$parameters);
        }
        if (method_exists($this->dompdf, $method)) {
            $return = $this->dompdf->{$method}(...$parameters);
            return $return == $this->dompdf ? $this : $return;
        }
        throw new \UnexpectedValueException("Method [{$method}] does not exist on PDF instance.");
    }
    /**
     * Make a safe fallback filename
     */
    protected function fallback_name(string $filename): string
    {
        return str_replace('%', '', Str::ascii($filename));
    }
}