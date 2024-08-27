<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\OrderBySku\Magewire;

use Elgentos\OrderBySku\Rules\ProductExistsRule;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component\Form;
use Magewirephp\Magewire\Exception\AcceptableException;
use Rakit\Validation\Validator;

class OrderBySku extends Form
{
    public array $fields = [];

    protected $rules = [
        'fields' => 'array',
        'fields.*.sku' => 'required|product_exists',
        'fields.*.qty' => 'required|numeric|min:1'
    ];

    protected $messages =
        [
            'fields.*.sku:required' => 'Sku is required',
            'fields.*.sku:product_exists' => 'Requested product does not exist',
            'fields.*.qty:required' => 'Quantity is required',
            'fields.*.qty:numeric' => 'Quantity needs to be a number',
            'fields.*.qty:min' => 'Quantity needs to be a minimum of 1',
        ];

    protected $loader = true;

    public function __construct(
        Validator $validator,
        private readonly ProductRepositoryInterface $productRepository,
        private CartRepositoryInterface $cartRepository,
        private Session $checkoutSession

    ) {
        parent::__construct($validator);
        if (!$validator->getValidator('product_exists')) {
            $validator->addValidator('product_exists', new ProductExistsRule($this->productRepository));
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $this->addFields();
    }

    public function addFields(): void
    {
        $this->fields[] = [
            'id' => md5((string)time()),
            'sku' => '',
            'qty' => 1,
        ];
    }

    public function removeFields(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);

        if (count($this->fields) === 0) {
            $this->addFields();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws AcceptableException
     * @throws LocalizedException
     */
    public function addToCart(): void
    {
        $this->validate();

        $quote = $this->getCheckoutSession();

        foreach ($this->fields as $field) {
            $product = $this->getProductBySku($field['sku']);

            $quote->addProduct($product, (int)$field['qty']);

            $this->cartRepository->save($quote);
        }
        $this->reset();
        $this->addFields();
        $this->emit('productAddedToCart');
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getProductBySku(string $sku): ?ProductInterface
    {
        return $this->productRepository->get($sku);
    }

    private function getCheckoutSession(): CartInterface | Quote
    {
        return $this->checkoutSession->getQuote();
    }
}
