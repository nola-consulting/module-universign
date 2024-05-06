<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Model;

use Dompdf\Dompdf;
use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Controller\AbstractResult;
use Magento\Framework\Filesystem\DirectoryList;

class PdfCreator extends AbstractResult
{
    protected string $fileName = 'contract.pdf';
    protected string $attachment = 'inline';
    public string $output = '';

    /**
     * The new Dompdf() is required to really reset the Dompdf class
     * and remove the data from php for multiple generations of pdf
     *
     * @param Dompdf $pdf
     * @param DirectoryList $directoryList
     */
    public function __construct(
        public Dompdf $pdf,
        protected DirectoryList $directoryList,
    ) {
        $this->pdf = new Dompdf();
    }

    /**
     * Load html
     *
     * @param $html
     */
    public function setData($html): void
    {
        $this->pdf->loadHtml($html);
    }

    /**
     * Set output from $this->renderOutput() to allow multiple renders
     *
     * @param $output
     */
    public function setOutput($output): void
    {
        $this->output = $output;
    }

    /**
     * Set filename
     *
     * @param $fileName
     */
    public function setFileName($fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * Set attachment type, either 'attachment' or 'inline'
     *
     * @param $mode
     */
    public function setAttachment($mode): void
    {
        $this->attachment = $mode;
    }

    /**
     * Render PDF output
     *
     * @return string|null
     */
    public function renderOutput(): ?string
    {
        $this->pdf->getOptions()->setChroot([$this->directoryList->getRoot() . '/pub']);

        if ($this->output) {
            return $this->output;
        }

        $this->pdf->render();

        return $this->pdf->output();
    }

    /**
     * Render PDF
     *
     * @param HttpResponseInterface $response
     *
     * @return $this
     */
    protected function render(HttpResponseInterface $response): self
    {
        $output = $this->renderOutput();

        $response->setHeader('Cache-Control', 'private');
        $response->setHeader('Content-type', 'application/pdf');
        $response->setHeader('Content-Length', mb_strlen($output, '8bit'));

        $filename = $this->fileName;
        $filename = str_replace(["\n", "'"], '', basename($filename, '.pdf')) . '.pdf';

        $encoding = mb_detect_encoding($filename);
        $fallbackfilename = mb_convert_encoding($filename, "ISO-8859-1", $encoding);
        $encodedfallbackfilename = rawurlencode($fallbackfilename);
        $encodedfilename = rawurlencode($filename);

        $response->setHeader(
            'Content-Disposition',
            $this->attachment . '; filename=' . $encodedfallbackfilename . "; filename*=UTF-8''" . $encodedfilename
        );

        $response->setBody($output);

        return $this;
    }
}
