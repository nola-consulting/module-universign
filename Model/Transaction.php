<?php
/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */

declare(strict_types=1);

namespace NolaConsulting\Universign\Model;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use NolaConsulting\Universign\Model\Api\Universign;

class Transaction
{

    public const SIGNER_EMAIL_CODE = 'email';
    public const SIGNER_FULL_NAME_CODE = 'full_name';
    public const SIGNER_PHONE_CODE = 'phone_number';
    public const SIGNER_COUNTRY_ID = 'country_id';

    public const SIGNATURE_X_CODE = 'x';
    public const SIGNATURE_Y_CODE = 'y';
    public const SIGNATURE_PAGE_CODE = 'page';
    public const SIGNATURE_NAME_CODE = 'name';

    public const ID_PLACEHOLDER = '%ID';

    private string $entityId = '';
    private string $transactionName = '';
    private string $documentName = '';
    private string $documentFullPath = '';
    private array $signer = [];
    private array $signature = [];
    private bool $sendEmail = true;

    private string $transactionId = '';
    private array $transactionData = [];


    /**
     * @param Config $config
     * @param Universign $universignApi
     * @param File $fileDriver
     * @param Logger $logger
     */
    public function __construct(
        private Config     $config,
        private Universign $universignApi,
        private File       $fileDriver,
        private Logger     $logger
    )
    {
    }

    /**
     * @param int|string $entityId
     *
     * @return $this
     */
    public function initialize(int|string $entityId = ''): self
    {
        if (!$this->config->isUniversignActive()) {
            $this->logger->error('Module is not active');
            return $this;
        }

        if (!$entityId) {
            $entityId = uniqid('', false);
        }

        $this->logger->info('Initialize transaction ' . $entityId);

        $this->setEntityId((string)$entityId);
        $this->setTransactionName();
        $this->setDocumentName();
        $this->setSignature();

        return $this;
    }

    /**
     *
     * @return array|null
     */
    public function create(): ?array
    {
        if (!$this->getEntityId()) {
            $this->logger->error('Transaction not initialized');
        }
        if (!$this->getDocumentFullPath()) {
            $this->logger->error('File not found');
        }
        if (empty($this->signer)) {
            $this->logger->error('Signer not defined');
        }

        $transaction = $this->universignApi->createTransaction($this->getTransactionName());
        if (!$transaction || !isset($transaction['id'])) {
            $this->logger->error('Impossible to initiate the transaction.');
            return null;
        }

        $this->setTransactionId($transaction['id']);

        $file = $this->universignApi->sendDocument($this->getDocumentName(), $this->getDocumentFullPath());
        if (!$file || !isset($file['id'])) {
            $this->logger->error('Impossible to initiate the document upload.');
            return null;
        }

        $fileId = $file['id'];
        $document = $this->universignApi->addDocumentToTransaction($this->getTransactionId(), $fileId);
        if (!$document || !isset($document['id'])) {
            $this->logger->error('Impossible to initiate the document association.');
            return null;
        }

        $signature = $this->universignApi->addSignatureToDocument($this->getTransactionId(), $document['id'], $this->getSignature());
        if (!$signature || !isset($signature['id'])) {
            $this->logger->error('Impossible to add the signature.');
            return null;
        }

        $this->universignApi->assignSignerToSignature($this->getTransactionId(), $this->getSignerEmail(), $signature['id']);

        $this->universignApi->enableSignerNotification($this->getTransactionId(), $this->getSigner(), $this->isSendEmail());

        $this->logger->info('Create transaction ' . $this->getTransactionId());

        $this->transactionData = $this->universignApi->startTransaction($this->getTransactionId());

        return $this->transactionData;
    }

    /**
     * @param string $transactionId
     *
     * @return array|null
     */
    public function getDataFromUniversign(string $transactionId): ?array
    {
        $this->setTransactionId($transactionId);
        $this->transactionData = $this->universignApi->getTransaction($transactionId);
        return $this->transactionData;
    }

    /**
     *
     * @return array
     */
    public function getTransactionData(): array
    {
        return $this->transactionData;
    }

    /**
     * @param string $transactionName
     *
     * @return $this
     */
    public function setTransactionName(string $transactionName = ''): self
    {
        if (!$transactionName) {
            $transactionName = $this->config->getDefaultTransactionName();
        }

        $this->transactionName = str_replace(self::ID_PLACEHOLDER, $this->getEntityId(), $transactionName);

        return $this;
    }

    /**
     *
     * @return string
     */
    public function getTransactionName(): string
    {
        return $this->transactionName;
    }

    /**
     * @param string $documentName
     *
     * @return $this
     */
    public function setDocumentName(string $documentName = ''): self
    {
        if (!$documentName) {
            $documentName = $this->config->getDefaultDocumentName();
        }

        $this->documentName = str_replace(self::ID_PLACEHOLDER, $this->getEntityId(), $documentName);

        return $this;
    }


    /**
     *
     * @return string
     */
    public function getDocumentName(): string
    {
        return $this->documentName;
    }

    /**
     * @param string $documentFullPath
     *
     * @return $this
     */
    public function setDocumentFullPath(string $documentFullPath = ''): self
    {
        try {
            if (!$documentFullPath || !$this->fileDriver->isExists($documentFullPath)) {
                $this->logger->error('File ' . $documentFullPath . ' was not found');
                return $this;
            }
        } catch (FileSystemException $exception) {
            $this->logger->error($exception->getMessage());
        }

        $this->documentFullPath = $documentFullPath;

        return $this;
    }

    /**
     * @param string $email
     * @param string $fullName
     * @param string $phone
     * @param string $countryId
     *
     * @return $this
     */
    public function setSigner(string $email, string $fullName, string $phone, string $countryId = ''): self
    {
        if (!$email || !$fullName || !$phone) {
            $this->logger->error('Email, name and phone of the signer are mandatory');
            return $this;
        }

        if (!$countryId) {
            $countryId = $this->config->getDefaultCountryId();
        }

        $this->signer = [
            self::SIGNER_EMAIL_CODE => $email,
            self::SIGNER_FULL_NAME_CODE => $fullName,
            self::SIGNER_PHONE_CODE => $phone,
            self::SIGNER_COUNTRY_ID => $countryId
        ];

        return $this;
    }

    /**
     *
     * @return array
     */
    public function getSigner(): array
    {
        return $this->signer;
    }

    /**
     *
     * @return string
     */
    public function getSignerEmail(): string
    {
        return $this->signer[self::SIGNER_EMAIL_CODE];
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $page
     * @param string $name
     *
     * @return $this
     */
    public function setSignature(int $x = 100, int $y = 100, int $page = 1, string $name = 'signature'): self
    {
        $this->signature = [
            self::SIGNATURE_X_CODE => $x,
            self::SIGNATURE_Y_CODE => $y,
            self::SIGNATURE_PAGE_CODE => $page,
            self::SIGNATURE_NAME_CODE => $name
        ];

        return $this;
    }

    /**
     *
     * @return array
     */
    public function getSignature(): array
    {
        return $this->signature;
    }

    /**
     *
     * @return string
     */
    public function getTransactionUrl(): string
    {
        return $this->universignApi->getTransactionUrl($this->transactionData, 'checkout/cart');
    }
    /**
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @param string $transactionId
     *
     * @return void
     */
    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /**
     *
     * @return string
     */
    public function getDocumentFullPath(): string
    {
        return $this->documentFullPath;
    }

    /**
     *
     * @return string
     */
    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * @param string $entityId
     *
     * @return void
     */
    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    /**
     *
     * @return bool
     */
    public function isSendEmail(): bool
    {
        return $this->sendEmail;
    }

    /**
     * @param bool $sendEmail
     *
     * @return void
     */
    public function setSendEmail(bool $sendEmail): void
    {
        $this->sendEmail = $sendEmail;
    }

}
