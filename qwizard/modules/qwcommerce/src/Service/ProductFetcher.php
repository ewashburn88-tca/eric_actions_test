<?php

namespace Drupal\qwcommerce\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

class ProductFetcher {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   *
   *  Fetch products with variations and selected fields/attributes.
   *
   * @param array $productFilters
   *      Supported keys (ALL OPTIONAL):
   *      - title (string|array): Matches product title (contains for string).
   *      - field_course (int|int[]): Entity ID(s).
   *      - field_premium (0|1|bool).
   *
   * @param array $variationFilters
   *      Supported keys (ALL OPTIONAL), applied against variation entity:
   *      - title (string|array)
   *      - field_membership_end_date (string|array)   // 'YYYY-MM-DD' or range
   *      ['min' => '','max' => '']
   *      - field_primary_product (0|1|bool)
   *      - field_revert_to_term_date (string|array)   // same as above
   *      - attribute_term (int|int[])                  // e.g., attribute
   *      field machine name 'attribute_term'
   *
   * @param array $options
   *      Options:
   *      - limit (int|null)   Default NULL (no limit)
   *      - offset (int)       Default 0
   *      - product_types (string|string[]) Restrict to bundle(s)
   *      - variation_status (0|1|bool|null) Filter variations by published
   *      status; null = no filter
   *
   * @return array
   *    Array of structured product data:
   *      [
   *        [
   *          'id' => 123,
   *          'title' => 'Name',
   *          'fields' => ['field_course' => 99, 'field_premium' => 1],
   *          'variations' => [
   *            [
   *              'id' => 456,
   *              'title' => 'Default',
   *              'fields' => [...],
   *              'attributes' => [
   *                // commerce attributes (auto fields like attribute_color)
   *                ['attribute' => 'attribute_term', 'value_id' => 8,
   *                'value_label' => 'Term A'],
   *              ],
   *            ],
   *          ],
   *        ],
   *      ]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProducts(array $productFilters = [], array $variationFilters = [], array $options = []): array {
    $storage = $this->entityTypeManager->getStorage('commerce_product');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1);

    // Optional: limit to bundles.
    if (!empty($options['product_types'])) {
      $bundles = is_array($options['product_types']) ? $options['product_types'] : [$options['product_types']];
      $query->condition('type', $bundles, 'IN');
    }

    // Product-level filters.
    $this->applyProductFilters($query, $productFilters);

    // Variation-level filters via referenced entity conditions.
    $this->applyVariationFilters($query, $variationFilters);

    // Limit/offset.
    if (!empty($options['limit'])) {
      $query->range((int) ($options['offset'] ?? 0), (int) $options['limit']);
    }

    // Sort newest first by id (tweak as needed).
    $query->sort('product_id', 'DESC');

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    /** @var \Drupal\commerce_product\Entity\ProductInterface[] $products */
    $products = $storage->loadMultiple($ids);

    $result = [];
    foreach ($products as $product) {
      $result[] = $this->normalizeProduct($product, $options);
    }

    return $result;
  }

  /**
   * Fetch products indexed by variation SKU.
   *
   * @param array $productFilters
   * @param array $variationFilters
   * @param array $options
   *
   * @return array
   *  Structure:
   *  [
   *  'SKU123' => [
   *  'attribute_info' => [  // variation-centric info
   *  'id' => 456,
   *  'title' => 'Monthly',
   *  'sku' => 'SKU123',
   *  'status' => 1,
   *  'price' => '99.00 USD',
   *  'fields' => [...],        // variation custom fields
   *  'attributes' => [...],    // attribute_* resolved labels/ids
   *  ],
   *  'product_info' => [   // parent product info
   *  'id' => 123,
   *  'type' => 'default',
   *  'title' => 'Course ABC',
   *  'fields' => [
   *  'field_course' => 99,
   *  'field_premium' => 1,
   *  ],
   *  ],
   *  ],
   *  // ...
   *  ]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProductsBySku(
    array $productFilters = [],
    array $variationFilters = [],
    array $options = []
  ): array {
    $bySku = [];

    // Reuse existing filtering + normalization.
    $products = $this->getProducts($productFilters, $variationFilters, $options);

    foreach ($products as $product) {
      foreach ($product['variations'] as $variation) {
        $sku = $variation['sku'] ?? null;
        if (!$sku) {
          continue;
        }
        $attributes = [];
        foreach ($variation['attributes'] as $attribute) {
          $attributes[$attribute['attribute']] = [
            'attribute_id' => $attribute['value_id'],
            'attribute_label' => $attribute['value_label'],
          ];
        }

        // NOTE: Commerce SKUs are expected to be unique per variation.
        // If you want to support duplicate SKUs, change this to $bySku[$sku][] = ...
        $bySku[$sku] = [
          'variation_info' => [
            'id' => $variation['id'],
            'title' => $variation['title'],
            'sku' => $variation['sku'],
            'status' => $variation['status'],
            'price' => $variation['price'],
            'fields' => $variation['fields'],
            'attributes' => $attributes,
          ],
          'product_info' => [
            'id' => $product['id'],
            'type' => $product['type'],
            'title' => $product['title'],
            'fields' => $product['fields'],
          ],
        ];
      }
    }

    return $bySku;
  }

  // ---------------------
  // Internals
  // ---------------------

  protected function applyProductFilters(\Drupal\Core\Entity\Query\QueryInterface $query, array $filters): void {
    // Title
    if (isset($filters['title'])) {
      if (is_array($filters['title'])) {
        $query->condition('title', $filters['title'], 'IN');
      }
      else {
        $query->condition('title', '%' . $filters['title'] . '%', 'LIKE');
      }
    }

    // field_course (entity reference target IDs)
    if (isset($filters['field_course'])) {
      $ids = is_array($filters['field_course']) ? $filters['field_course'] : [$filters['field_course']];
      $query->condition('field_course.target_id', $ids, 'IN');
    }

    // field_premium (boolean)
    if (isset($filters['field_premium'])) {
      $query->condition('field_premium', (int) (bool) $filters['field_premium']);
    }
  }

  protected function applyVariationFilters(\Drupal\Core\Entity\Query\QueryInterface $query, array $filters): void {
    // Variation title
    if (isset($filters['title'])) {
      if (is_array($filters['title'])) {
        $query->condition('variations.entity:commerce_product_variation.title', $filters['title'], 'IN');
      }
      else {
        $query->condition('variations.entity:commerce_product_variation.title', '%' . $filters['title'] . '%', 'LIKE');
      }
    }

    // field_membership_end_date (date string or ['min'=>, 'max'=>])
    if (isset($filters['field_membership_end_date'])) {
      $this->applyDateFilter($query, 'variations.entity:commerce_product_variation.field_membership_end_date', $filters['field_membership_end_date']);
    }

    // field_primary_product (boolean)
    if (isset($filters['field_primary_product'])) {
      $query->condition('variations.entity:commerce_product_variation.field_primary_product', (int) (bool) $filters['field_primary_product']);
    }

    // field_revert_to_term_date (date)
    if (isset($filters['field_revert_to_term_date'])) {
      $this->applyDateFilter($query, 'variations.entity:commerce_product_variation.field_revert_to_term_date', $filters['field_revert_to_term_date']);
    }

    // attribute_term (entity reference to a term or attribute value)
    if (isset($filters['attribute_term'])) {
      $ids = is_array($filters['attribute_term']) ? $filters['attribute_term'] : [$filters['attribute_term']];
      $query->condition('variations.entity:commerce_product_variation.attribute_term.target_id', $ids, 'IN');
    }
  }

  protected function applyDateFilter(\Drupal\Core\Entity\Query\QueryInterface $query, string $field, $value): void {
    if (is_array($value)) {
      if (!empty($value['min'])) {
        $query->condition($field, $value['min'], '>=');
      }
      if (!empty($value['max'])) {
        $query->condition($field, $value['max'], '<=');
      }
    }
    elseif (!empty($value)) {
      $query->condition($field, $value);
    }
  }

  protected function normalizeProduct(ProductInterface $product, array $options): array {
    $fields = [
      'field_course' => $this->refId($product, 'field_course'),
      'field_premium' => $this->boolVal($product, 'field_premium'),
    ];

    $variations_out = [];
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations */
    $variations = $product->get('variations')->referencedEntities();

    foreach ($variations as $variation) {
      if (isset($options['variation_status']) && $options['variation_status'] !== NULL) {
        if ((int) (bool) $variation->isPublished() !== (int) (bool) $options['variation_status']) {
          continue;
        }
      }
      $variations_out[] = $this->normalizeVariation($variation);
    }

    return [
      'id' => (int) $product->id(),
      'type' => $product->bundle(),
      'title' => $product->getTitle(),
      'fields' => $fields,
      'variations' => $variations_out,
    ];
  }

  protected function normalizeVariation(ProductVariationInterface $variation): array {
    $fields = [
      'field_membership_end_date' => $this->dateVal($variation, 'field_membership_end_date'),
      'field_primary_product' => $this->boolVal($variation, 'field_primary_product'),
      'field_revert_to_term_date' => $this->dateVal($variation, 'field_revert_to_term_date'),
      'attribute_term' => $this->refId($variation, 'attribute_term'),
      // if present in your site
    ];

    // Collect Commerce attributes (fields like attribute_color, attribute_size, etc).
    $attributes = [];
    foreach ($variation->getFields() as $field_name => $field) {
      if (str_starts_with($field_name, 'attribute_') && !$field->isEmpty()) {
        $target_id = $field->target_id ?? NULL;
        $label = NULL;
        try {
          if (method_exists($variation, 'getAttributeValue') && $target_id) {
            $value_entity = $variation->get('' . $field_name)->entity;
            $label = $value_entity ? $value_entity->label() : NULL;
          }
        }
        catch (\Throwable $e) {
          // Ignore attribute resolution errors.
        }

        $attributes[] = [
          'attribute' => $field_name,
          'value_id' => $target_id,
          'value_label' => $label,
        ];
      }
    }

    return [
      'id' => (int) $variation->id(),
      'type' => $variation->bundle(),
      'title' => $variation->getTitle(),
      'sku' => $variation->getSku(),
      'status' => (int) $variation->isPublished(),
      'price' => method_exists($variation, 'getPrice') && $variation->getPrice() ? $variation->getPrice()
        ->__toString() : NULL,
      'fields' => $fields,
      'attributes' => $attributes,
    ];
  }

  // ---------------------
  // Field helpers
  // ---------------------

  protected function refId($entity, string $field): mixed {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? (int) $entity->get($field)->target_id
      : NULL;
  }

  protected function boolVal($entity, string $field): ?int {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? (int) ((bool) $entity->get($field)->value)
      : NULL;
  }

  protected function dateVal($entity, string $field): ?string {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? (string) $entity->get($field)->value
      : NULL;
  }

}

