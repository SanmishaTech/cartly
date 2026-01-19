<?php

namespace App\Controllers;

use Slim\Psr7\Response;
use Slim\Views\Twig;

class StorefrontController
{
    public function __construct(
        protected Twig $view
    ) {}

    public function products($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $products = $this->placeholderProducts();
        $categories = $this->placeholderCategories();

        return $this->view->render($response, 'pages/products.twig', [
            'shop' => $request->getAttribute('shop'),
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function productDetail($request, Response $response, array $args): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $slug = $args['slug'] ?? 'product';
        $product = $this->placeholderProductFromSlug($slug);

        return $this->view->render($response, 'pages/product.twig', [
            'shop' => $request->getAttribute('shop'),
            'product' => $product,
        ]);
    }

    public function categories($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/categories.twig', [
            'shop' => $request->getAttribute('shop'),
            'categories' => $this->placeholderCategories(),
        ]);
    }

    public function categoryDetail($request, Response $response, array $args): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $slug = $args['slug'] ?? 'category';
        $category = $this->placeholderCategoryFromSlug($slug);

        return $this->view->render($response, 'pages/category.twig', [
            'shop' => $request->getAttribute('shop'),
            'category' => $category,
            'products' => $this->placeholderProducts(),
        ]);
    }

    public function cart($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/cart.twig', [
            'shop' => $request->getAttribute('shop'),
            'cart_items' => $this->placeholderCartItems(),
        ]);
    }

    public function checkout($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/checkout.twig', [
            'shop' => $request->getAttribute('shop'),
            'cart_items' => $this->placeholderCartItems(),
        ]);
    }

    public function account($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/account.twig', [
            'shop' => $request->getAttribute('shop'),
            'orders' => $this->placeholderOrders(),
        ]);
    }

    private function renderLandingIfNoShop($request, Response $response): ?Response
    {
        $shop = $request->getAttribute('shop');
        if ($shop) {
            return null;
        }

        return $this->view->render($response, 'home.twig');
    }

    private function placeholderProducts(): array
    {
        return [
            [
                'name' => 'Classic Cotton T-Shirt',
                'slug' => 'classic-cotton-tshirt',
                'price' => '₹899',
                'badge' => 'New',
            ],
            [
                'name' => 'Wireless Earbuds',
                'slug' => 'wireless-earbuds',
                'price' => '₹2,499',
                'badge' => 'Hot',
            ],
            [
                'name' => 'Minimal Backpack',
                'slug' => 'minimal-backpack',
                'price' => '₹1,799',
                'badge' => 'Limited',
            ],
            [
                'name' => 'Ceramic Coffee Mug',
                'slug' => 'ceramic-coffee-mug',
                'price' => '₹499',
                'badge' => 'Bestseller',
            ],
            [
                'name' => 'Smart Fitness Band',
                'slug' => 'smart-fitness-band',
                'price' => '₹3,199',
                'badge' => 'Trending',
            ],
            [
                'name' => 'Wooden Desk Organizer',
                'slug' => 'wooden-desk-organizer',
                'price' => '₹1,099',
                'badge' => 'Eco',
            ],
            [
                'name' => 'Studio Headphones',
                'slug' => 'studio-headphones',
                'price' => '₹4,699',
                'badge' => 'Top Rated',
            ],
            [
                'name' => 'Scented Candle Set',
                'slug' => 'scented-candle-set',
                'price' => '₹1,299',
                'badge' => 'Gift',
            ],
        ];
    }

    private function placeholderCategories(): array
    {
        return [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'count' => '128 items',
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'count' => '342 items',
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'count' => '98 items',
            ],
            [
                'name' => 'Beauty',
                'slug' => 'beauty',
                'count' => '56 items',
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'count' => '74 items',
            ],
            [
                'name' => 'Grocery',
                'slug' => 'grocery',
                'count' => '210 items',
            ],
            [
                'name' => 'Kids',
                'slug' => 'kids',
                'count' => '88 items',
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'count' => '152 items',
            ],
        ];
    }

    private function placeholderCartItems(): array
    {
        return [
            [
                'name' => 'Classic Cotton T-Shirt',
                'price' => '₹899',
                'qty' => 1,
            ],
            [
                'name' => 'Minimal Backpack',
                'price' => '₹1,799',
                'qty' => 1,
            ],
        ];
    }

    private function placeholderOrders(): array
    {
        return [
            [
                'id' => 'ORD-1245',
                'date' => '12 Jan 2026',
                'status' => 'Delivered',
                'total' => '₹3,498',
            ],
            [
                'id' => 'ORD-1189',
                'date' => '02 Jan 2026',
                'status' => 'Processing',
                'total' => '₹1,299',
            ],
        ];
    }

    private function placeholderProductFromSlug(string $slug): array
    {
        $name = ucwords(str_replace('-', ' ', $slug));
        return [
            'name' => $name ?: 'Product',
            'slug' => $slug ?: 'product',
            'price' => '₹2,199',
            'badge' => 'Placeholder',
            'sku' => 'SKU-PLACEHOLDER',
            'stock' => 'In Stock',
        ];
    }

    private function placeholderCategoryFromSlug(string $slug): array
    {
        $name = ucwords(str_replace('-', ' ', $slug));
        return [
            'name' => $name ?: 'Category',
            'slug' => $slug ?: 'category',
            'description' => 'This is a placeholder category description. Replace with your collection story.',
        ];
    }
}
