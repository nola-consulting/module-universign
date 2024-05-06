<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Block\Pdf;

use Magento\Framework\View\Element\Template;

class Contract extends Template
{
    /**
     * By default, PDF files will be stored directly in /var
     */
    public const PATH_IN_VAR = '/';

    /**
     * @var string
     */
    protected $_template = "NolaConsulting_Universign::pdf/demo-contract.phtml";

    /**
     * @var string
     */
    private string $fullName = '';

    /**
     *
     * @return string
     */
    public function getFullName(): string
    {
        return $this->fullName;
    }

    /**
     * @param string $fullName
     *
     * @return void
     */
    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }


}
