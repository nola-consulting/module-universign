<?php

declare(strict_types=1);

namespace NolaConsulting\Universign\Model\Api;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use NolaConsulting\Universign\Model\Config;
use NolaConsulting\Universign\Model\Logger;
use NolaConsulting\Universign\Model\Transaction;

/**
 * @author    Nola Consulting <nolasoftware@nolaconsulting.fr>
 * @copyright 2024-present Nola Consulting
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.nolaconsulting.fr/
 */
class Universign
{

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     */
    public function __construct(
        private Config                $config,
        private StoreManagerInterface $storeManager,
        private Logger                $logger
    )
    {
    }

    /**
     * @param string $urlAction
     * @param bool $isPost
     * @param array $data
     * @param array $headers
     * @param bool $isRawDocument
     *
     * @return array|null
     */
    public function call(string $urlAction, bool $isPost = false, array $data = [], array $headers = [], bool $isRawDocument = false): ?array
    {
        if (!$this->config->isUniversignActive()) {
            $this->logger->error('Module is not active');
            return null;
        }

        $resultArray = null;

        $connection = curl_init($this->config->getApiUrl() . $urlAction);

        $allHeaders = array_merge($this->getDefaultHeaders(), $headers);

        curl_setopt($connection, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($connection, CURLOPT_TIMEOUT, 5);
        if ($isPost) {
            curl_setopt($connection, CURLOPT_POST, true);
        }
        if (count($data)) {
            curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($connection);
        curl_close($connection);

        if ($response && is_string($response)) {
            try {
                if ($isRawDocument) {
                    if (str_starts_with($response, '{')) {
                        $jsonResponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                        $this->logger->error('Fail to download file: ' . $jsonResponse['error_description']);
                        return null;
                    }
                    return ['document' => $response];
                }
                $resultArray = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $exception) {
                $this->logger->error('Fail to decode response');
                $this->logger->error($exception->getMessage());
            }

        }

        return $resultArray;
    }


    /**
     * @param string $transactionName
     *
     * @return array|null
     */
    public function createTransaction(string $transactionName): ?array
    {
        $action = 'transactions';

        $data = ['name' => $transactionName];

        return $this->call($action, true, $data);
    }

    /**
     * @param string $transactionId
     *
     * @return array|null
     */
    public function getTransaction(string $transactionId): ?array
    {
        $action = 'transactions/' . $transactionId;

        return $this->call($action);
    }

    /**
     * @param string $documentName
     * @param string $documentPath
     * @return array|null
     */
    public function sendDocument(string $documentName, string $documentPath): ?array
    {
        $action = 'files';

        if (!$documentName) {
            $parts = explode('/', $documentPath);
            $documentName = $parts[count($parts) - 1];
        }

        $curlFile = curl_file_create($documentPath, '	application/pdf', $documentName);
        $data = ['file' => $curlFile];

        $headers = ['Content-Type: multipart/form-data'];

        return $this->call($action, true, $data, $headers);
    }

    /**
     * @param string $transactionId
     * @param string $fileId
     *
     * @return array|null
     */
    public function addDocumentToTransaction(string $transactionId, string $fileId): ?array
    {
        $action = 'transactions/' . $transactionId . '/documents';

        $data = ['document' => $fileId];

        return $this->call($action, true, $data);
    }

    /**
     * @param string $transactionId
     * @param string $documentId
     * @param array $data
     *
     * @return array|null
     */
    public function addSignatureToDocument(string $transactionId, string $documentId, array $data): ?array
    {
        $action = 'transactions/' . $transactionId . '/documents/' . $documentId . '/fields';

        return $this->call($action, true, $data);
    }

    /**
     * @param string $transactionId
     * @param string $signerEmail
     * @param string $signatureId
     *
     * @return array|null
     */
    public function assignSignerToSignature(string $transactionId, string $signerEmail, string $signatureId): ?array
    {
        $action = 'transactions/' . $transactionId . '/signatures';

        $data = [
            'signer' => $signerEmail,
            'field' => $signatureId
        ];

        return $this->call($action, true, $data);
    }

    /**
     * @param string $transactionId
     * @param array $signer Attributes: email (required), full_name and phone_number
     * @param bool $sendEmail
     * @return array|null
     */
    public function enableSignerNotification(string $transactionId, array $signer, bool $sendEmail = false): ?array
    {
        $action = 'transactions/' . $transactionId . '/participants';

        $data = [
            Transaction::SIGNER_EMAIL_CODE => $signer[Transaction::SIGNER_EMAIL_CODE],
            'schedule' => '', // [0]
        ];

        if (!$sendEmail) {
            $data['access_control'] = 'none';
            $data['send_signed_documents_mail'] = 'false';
        }
        if (isset($signer[Transaction::SIGNER_FULL_NAME_CODE])) {
            $data[Transaction::SIGNER_FULL_NAME_CODE] = $signer[Transaction::SIGNER_FULL_NAME_CODE];
        }
        if (isset($signer[Transaction::SIGNER_PHONE_CODE]) && $signer[Transaction::SIGNER_PHONE_CODE]) {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($signer[Transaction::SIGNER_PHONE_CODE], $signer[Transaction::SIGNER_COUNTRY_ID]);
            } catch (NumberParseException $e) {
                $this->logger->error('Failed to parse phone number');
                $this->logger->error($e->getMessage());
                return null;
            }
            $data[Transaction::SIGNER_PHONE_CODE] = $phoneNumberUtil->format($phoneNumberObject,PhoneNumberFormat::E164);
        }

        return $this->call($action, true, $data);
    }

    /**
     * @param string $transactionId
     *
     * @return array|null
     */
    public function startTransaction(string $transactionId): ?array
    {
        $action = 'transactions/' . $transactionId . '/start';

        return $this->call($action, true);
    }

    /**
     * @param array $transaction
     *
     * @return string|null
     */
    public function downloadSignedDocument(array $transaction): ?string
    {
        $transactionId = $transaction['id'];
        $documentId = $transaction['documents'][0]['id'];
        $action = 'transactions/' . $transactionId . '/archive/documents/' . $documentId . '/download';

        $response = $this->call($action, false, [], [], true);

        return $response['document'];
    }

    /**
     * @param string|array $transaction Transaction ID or Transaction Array
     *
     * @return bool
     */
    public function isTransactionCompleted(string|array $transaction): bool
    {
        if (!is_array($transaction)) {
            $transaction = $this->getTransaction($transaction);
        }

        return $transaction &&
            isset($transaction['state']) &&
            in_array($transaction['state'], ['completed', 'closed']);
    }

    /**
     * @param string|array $transaction Transaction ID or Transaction Array
     * @param string $returnUrl
     * @return string
     */
    public function getTransactionUrl(string|array $transaction, string $returnUrl): string
    {
        if (!is_array($transaction)) {
            $transaction = $this->getTransaction($transaction);
        }

        if (isset($transaction['actions']) && is_array($transaction['actions'])) {
            $actions = $transaction['actions'];
            $firstAction = reset($actions);
            if (isset($firstAction['url'])) {
                try {
                    $fullReturnUrl = $this->storeManager->getStore()->getBaseUrl();
                } catch (NoSuchEntityException $e) {
                    $this->logger->error('Failed to get base URL');
                    $this->logger->error($e->getMessage());
                    return '';
                }

                if ($returnUrl) {
                    $fullReturnUrl .= $returnUrl;
                }
                return $firstAction['url'] . '?redirect_url=' . $fullReturnUrl;
            }
        }
        return '';
    }

    /**
     *
     * @return string[]
     */
    public function getDefaultHeaders(): array
    {
        $key = $this->config->getApiKey();
        if (!$key) {
            $this->logger->error('API Key is not configured');
        }

        return ['Authorization: Bearer ' . $key];
    }


}
