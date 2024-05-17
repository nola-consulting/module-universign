# NolaConsulting_Universign

NolaConsulting_Universign is a Magento module allowing you and your customers to sign documents with Universign, an eIDAS qualified Trust Service Provider. A Universign account is required to use this module.

## Installation

    composer require nola-consulting/module-universign
    bin/magento module:enable NolaConsulting_Universign
    bin/magento setup:upgrade

## Configuration

Go to
`STORES > Configuration > SERVICES > Universign`

You can find your API key on the Universign website, in your account under 
`Developer > API keys.`

You can set a default `Country ID` for your website, it is used to format the phone number with the right country code.
If your website has customers from different countries, you can specify the `$countryId` in the `setSigner()` method.

You can set a default `Transaction Name` and a default `Document Name` there as well. If you have many types of documents to sign, you can specify them when you initialize the transaction:

```php
$this->transaction->initialize($documentId)
    ->setDocumentFullPath($documentFullPath)
    ->setTransactionName('Contract N°%ID')
    ->setDocumentName('contract-%ID.pdf')
    ->setSigner($email, $fullName, $phone)
    ->create();
```

The `%ID` placeholder will be replaced by the reference passed to `initialize()`.

## Demo
It's possible to test the module at the url `/universign/demo`, you can:
- Create a "Contact" and sign it after providing a name, an email and a phone.
- Retrieve transaction data by providing the transaction ID.

## Basic usage

```php
use NolaConsulting\Universign\Model\TransactionFactory;

/** ... */

/** @var Transaction $transaction */
$this->transaction = $this->transactionFactory->create();

$this->transaction->initialize($documentId)
    ->setDocumentFullPath($documentFullPath)
    ->setSigner($email, $fullName, $phone)
    ->create();

$redirectUrl = $this->transaction->getTransactionUrl();
```

## Dependencies

- `Dompdf`: help you to generate PDF from HTML.
- `libphonenumber`: used to format the phone number in the E.164 format required by Universign for any country.

## Troubleshooting

    Exception #0 (ReflectionException): Class "NolaConsulting\Universign\Model\PdfCreator\Interceptor" does not exist

Cause: Dompdf probably hasn't been installed properly.

## TODO

- Manage automatic reminders with the `schedule` array
- Allow customers to sign documents only with a phone number and without email


