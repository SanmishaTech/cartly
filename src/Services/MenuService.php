<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Shop;

class MenuService
{
    private const LOCATIONS = ['header', 'footer_quick', 'footer_customer'];

    public function getMenusForShop(Shop $shop): array
    {
        $menus = [];
        foreach (self::LOCATIONS as $location) {
            $menus[$location] = $this->getMenuItemsForLocation($shop, $location);
        }
        return $menus;
    }

    public function getMenuItemsForLocation(Shop $shop, string $location): array
    {
        if (!in_array($location, self::LOCATIONS, true)) {
            return [];
        }

        $menu = Menu::where('shop_id', $shop->id)
            ->where('location', $location)
            ->first();

        $items = [];
        if ($menu) {
            foreach ($menu->items()->get() as $item) {
                if ($item->type === 'page') {
                    $page = Page::where('shop_id', $shop->id)
                        ->where('type', 'standard')
                        ->where('status', 'published')
                        ->where('id', $item->page_id)
                        ->first();
                    if (!$page) {
                        continue;
                    }
                    $label = $item->label !== '' ? $item->label : $page->title;
                    $items[] = [
                        'label' => $label,
                        'url' => '/' . ltrim($page->slug, '/'),
                        'type' => 'page',
                    ];
                    continue;
                }

                if ($item->type === 'url' && $item->url) {
                    $items[] = [
                        'label' => $item->label,
                        'url' => $item->url,
                        'type' => 'url',
                    ];
                }
            }
        }

        if (!empty($items)) {
            return $items;
        }

        return $this->fallbackPages($shop);
    }

    private function fallbackPages(Shop $shop): array
    {
        $pages = Page::where('shop_id', $shop->id)
            ->where('type', 'standard')
            ->where('status', 'published')
            ->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->orderBy('title')
            ->get();

        $items = [];
        foreach ($pages as $page) {
            $items[] = [
                'label' => $page->title,
                'url' => '/' . ltrim($page->slug, '/'),
                'type' => 'page',
            ];
        }

        return $items;
    }
}
