<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Controller\Demo;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use NolaConsulting\Universign\Model\Config;

class Index implements HttpGetActionInterface
{

    /**
     * @param ResultFactory $resultFactory
     * @param RedirectFactory $redirectFactory
     * @param Config $config
     */
    public function __construct(
        private ResultFactory      $resultFactory,
        private RedirectFactory    $redirectFactory,
        private Config             $config,
    )
    {
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isUniversignActive() || !$this->config->isDemoActive()) {
            return $redirect->setPath('/');
        }

        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }

}
