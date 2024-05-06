<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Model;

use Psr\Log\LoggerInterface;

class Logger
{

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger,
    )
    {
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function info(string $message): void
    {
        $this->logger->debug('UNIVERSIGN INFO: ' . $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function error(string $message): void
    {
        $this->logger->debug('UNIVERSIGN ERROR: ' . $message);
    }
}
