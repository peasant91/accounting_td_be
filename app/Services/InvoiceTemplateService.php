<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceTemplate;
use Illuminate\Support\Arr;

class InvoiceTemplateService
{
    /**
     * Get the default components list from config.
     */
    public function getDefaultComponents(): array
    {
        return config('invoice.default_components', []);
    }

    /**
     * Resolve locale info from customer's currency.
     */
    public function resolveLocale(string $currency): array
    {
        $map = config('invoice.currency_locale_map', []);
        $localeConfig = $map[$currency] ?? $map['USD']; // Fallback to USD/English

        $labels = config("invoice.labels.{$localeConfig['language']}", config('invoice.labels.en'));

        return array_merge($localeConfig, ['labels' => $labels]);
    }

    /**
     * Get template for customer, or default if none exists.
     */
    public function getTemplateForCustomer(Customer $customer): array
    {
        $template = $customer->invoiceTemplate;
        $savedComponents = $template ? $template->components : [];

        // Convert saved components to a map for easy lookup
        $savedMap = [];
        if (is_array($savedComponents)) {
            foreach ($savedComponents as $comp) {
                if (isset($comp['key'])) {
                    $savedMap[$comp['key']] = $comp['enabled'] ?? true;
                }
            }
        }

        $defaultComponents = $this->getDefaultComponents();
        $mergedComponents = [];

        foreach ($defaultComponents as $default) {
            $key = $default['key'];
            // If saved, use saved 'enabled'. If not saved, use default 'enabled'.
            $enabled = $savedMap[$key] ?? $default['enabled'];

            // Should always respect 'required' -> cannot enable if required is true? No, required means enabled=true always?
            // "required": true means it cannot be disabled.
            // But if the user somehow saved it as disabled, we should probably force it to enabled?
            // The validation handles saving, but reading back we should also enforce it just in case.
            if ($default['required']) {
                $enabled = true;
            }

            $mergedComponents[] = [
                'key' => $key,
                'label' => $default['label'],
                'enabled' => $enabled,
                'required' => $default['required'],
            ];
        }

        $resolvedLocale = $this->resolveLocale($customer->currency ?? 'USD');

        return [
            'id' => $template?->id,
            'customer_id' => $customer->id,
            'components' => $mergedComponents,
            'resolved_locale' => $resolvedLocale,
            'created_at' => $template?->created_at,
            'updated_at' => $template?->updated_at,
        ];
    }

    /**
     * Upsert the template components config.
     */
    public function saveTemplate(Customer $customer, array $components): InvoiceTemplate
    {
        $toSave = [];
        $defaultComponents = $this->getDefaultComponents();
        $allowedKeys = array_column($defaultComponents, 'key');

        foreach ($components as $comp) {
            if (in_array($comp['key'], $allowedKeys)) {
                $toSave[] = [
                    'key' => $comp['key'],
                    'enabled' => (bool) $comp['enabled'],
                ];
            }
        }

        return $customer->invoiceTemplate()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['components' => $toSave]
        );
    }

    /**
     * Get preview data with localized labels + sample invoice.
     */
    public function getPreviewData(Customer $customer): array
    {
        $templateData = $this->getTemplateForCustomer($customer);
        $locale = $templateData['resolved_locale'];

        // Determine values based on locale
        $lang = $locale['language']; // 'en', 'id', or 'ja'

        $descriptions = [
            'en' => ['Game Development Eating Game - October', 'Fansite Maintenance - October'],
            'id' => ['Game Development Eating Game - Oktober', 'Fansite Maintenance - Oktober'],
            'ja' => ['ウェブ開発サービス - 10月', 'ファンサイト保守 - 10月'],
        ];

        $bankNames = [
            'en' => 'Bank Mandiri',
            'id' => 'Bank Mandiri',
            'ja' => '三菱UFJ銀行',
        ];

        $desc = $descriptions[$lang] ?? $descriptions['en'];
        $bankName = $bankNames[$lang] ?? $bankNames['en'];

        $sampleInvoice = [
            'invoice_number' => 'INV-2026-0001',
            'invoice_date' => now()->format('Y-m-d'),
            'customer_name' => $customer->company_name ?? $customer->name,
            'company_name' => 'Five Tag Co., Ltd.',
            'sender' => [
                'company_name' => 'PT. Timedoor Indonesia',
                'address' => 'Jl. Tukad Yeh Aya IX no. 46',
                'phone' => '+62-811-3898-004',
                'email' => 'info@timedoor.net',
                'npwp' => '71.853.950.5-903.000',
            ],
            'items' => [
                [
                    'description' => $desc[0],
                    'quantity' => 1,
                    'unit_price' => 8000000,
                    'amount' => 8000000,
                ],
                [
                    'description' => $desc[1],
                    'quantity' => 1,
                    'unit_price' => 5500000,
                    'amount' => 5500000,
                ],
            ],
            'total' => 13500001,
            'currency' => $customer->currency ?? 'USD',
            'bank_info' => [
                'bank_name' => $bankName,
                'swift_code' => 'BMRIIDJA',
                'account_name' => 'PT. TIMEDOOR INDONESIA',
                'account_number' => '145-00-0111141-4',
            ],
        ];

        return [
            'template' => $templateData,
            'locale' => $locale,
            'sample_invoice' => $sampleInvoice,
        ];
    }
}
