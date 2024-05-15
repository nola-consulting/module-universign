<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Block;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutFactory;
use NolaConsulting\Universign\Model\PdfCreatorFactory;
use NolaConsulting\Universign\Model\TransactionFactory;
use NolaConsulting\Universign\Block\Pdf\Contract;
use NolaConsulting\Universign\Model\Logger;
use NolaConsulting\Universign\Model\Transaction;

class Demo extends Template
{

    /**
     * @var Transaction
     */
    protected Transaction $transaction;

    /**
     * @param Template\Context $context
     * @param TransactionFactory $transactionFactory
     * @param Logger $logger
     * @param DirectoryList $dir
     * @param ResponseInterface $response
     * @param RedirectInterface $redirect
     * @param LayoutFactory $layoutFactory
     * @param Filesystem $filesystem
     * @param PdfCreatorFactory $pdfCreatorFactory
     * @param ManagerInterface $messageManager
     * @param array $data
     */
    public function __construct(
        Template\Context           $context,
        private TransactionFactory $transactionFactory,
        private Logger             $logger,
        private DirectoryList      $dir,
        private ResponseInterface  $response,
        private RedirectInterface  $redirect,
        private LayoutFactory      $layoutFactory,
        private Filesystem         $filesystem,
        private PdfCreatorFactory  $pdfCreatorFactory,
        private ManagerInterface   $messageManager,
        array                      $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        if ($this->response->getHeader('Location')) {
            return parent::_prepareLayout();
        }

        /** @var Transaction $transaction */
        $this->transaction = $this->transactionFactory->create();

        if ($this->getRequest()->getParam('transaction_id')) {
            $transactionId = $this->getRequest()->getParam('transaction_id');
            $this->transaction->getDataFromUniversign($transactionId);
        }

        if ($this->getRequest()->getParam('full_name') &&
            $this->getRequest()->getParam('email') &&
            $this->getRequest()->getParam('phone')) {
            $fullName = $this->getRequest()->getParam('full_name');
            $email = $this->getRequest()->getParam('email');
            $phone = $this->getRequest()->getParam('phone');
            $countryId = $this->getRequest()->getParam('country_id');

            try {
                $pdfPath = $this->createPdf($fullName);
            } catch (FileSystemException $e) {
                $this->logger->error('Failed to create PDF file');
                $this->logger->error($e->getMessage());
            }

            if (isset($pdfPath) && $pdfPath) {
                try {
                    $documentFullPath = $this->dir->getPath('var') . $pdfPath;
                } catch (FileSystemException $e) {
                    $this->logger->error('Failed to get document full path');
                    $this->logger->error($e->getMessage());
                    return parent::_prepareLayout();
                }

                $this->transaction->initialize(uniqid('', false))
                    ->setDocumentFullPath($documentFullPath)
                    ->setSigner($email, $fullName, $phone, $countryId)
                    ->create();

                $redirectUrl = $this->transaction->getTransactionUrl();
                $transactionData = $this->getTransactionData();
                $errorDescription = $transactionData['error_description'] ?? '';

                if ($redirectUrl && !$errorDescription) {
                    $this->response->setRedirect($redirectUrl);
                    $this->redirect->redirect($this->response, $redirectUrl);
                } else {
                    $this->response->setRedirect($this->redirect->getRefererUrl());
                    $this->redirect->redirect($this->response, $this->redirect->getRefererUrl());
                    $this->logger->error('Fail to retrieve redirect URL (' . $errorDescription . ')');
                    $this->messageManager->addErrorMessage('Fail to retrieve redirect URL (' . $errorDescription . ')');
                }
            }
        }

        return parent::_prepareLayout();
    }


    /**
     * Will create a PDF in the /var directory and returns its path
     *
     * @param string $fullName
     * @return string
     * @throws FileSystemException
     */
    protected function createPdf(string $fullName): string
    {
        /** @var Contract $block */
        $layout = $this->layoutFactory->create();
        $contractBlock = $layout->createBlock(Contract::class);
        $contractBlock->setFullName($fullName);
        $contractHtml = $contractBlock->toHtml();

        $pdfName = 'demo-contract-' . uniqid('', false) . '.pdf';

        $response = $this->pdfCreatorFactory->create();
        $response->setData($contractHtml);
        $response->setFileName($pdfName);
        $directory = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);

        $pdfPathInVar = Contract::PATH_IN_VAR . $pdfName;

        $directory->writeFile($pdfPathInVar, $response->renderOutput());

        return $pdfPathInVar;
    }

    /**
     *
     * @return Transaction|null
     */
    public function getTransaction(): ?Transaction
    {
        return $this->transaction ?? null;
    }

    /**
     *
     * @return array
     */
    public function getTransactionData(): array
    {
        if (isset($this->transaction)) {
            return $this->transaction->getTransactionData();
        }
        return [];
    }

    /**
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return (string)$this->getRequest()->getParam('transaction_id');
    }

}
