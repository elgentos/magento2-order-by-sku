<?php

/**
 * Copyright Elgentos. All rights reserved.
 * https://elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\OrderBySku\Rules;


use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Rakit\Validation\Rule;

class ProductExistsRule extends Rule
{
    protected $message = ":attribute does not exist";


    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function check($value): bool
    {
        try {
            $product = $this->productRepository->get($value);
        } catch (NoSuchEntityException $e) {
            $product = false;
        }
        return (bool)$product;
    }
}
