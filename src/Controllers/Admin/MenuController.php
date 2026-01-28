<?php

namespace App\Controllers\Admin;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Shop;
use Slim\Psr7\Response;

class MenuController extends AppController
{
    private const LOCATIONS = ['header', 'footer_quick', 'footer_customer'];
    private const ITEM_TYPES = ['page', 'url'];

    public function edit($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $menus = Menu::where('shop_id', $shop->id)->get()->keyBy('location');
        $menuItems = [];
        foreach (self::LOCATIONS as $location) {
            $menu = $menus->get($location);
            $menuItems[$location] = $menu
                ? $menu->items()->get()->map(fn(MenuItem $item) => [
                    'label' => $item->label,
                    'type' => $item->type,
                    'page_id' => $item->page_id,
                    'url' => $item->url,
                ])->toArray()
                : [];
        }

        $pages = Page::where('shop_id', $shop->id)
            ->where('type', 'standard')
            ->orderBy('title')
            ->get();

        return $this->render($response, 'menus/edit.twig', [
            'menus' => $menuItems,
            'pages' => $pages,
            'errors' => $this->flashGet('errors', []),
            'data' => $this->flashGet('old', []),
        ]);
    }

    public function update($request, Response $response): Response
    {
        $shop = $this->getShopOrRedirect($response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = (array)$request->getParsedBody();
        $menuInput = $data['menu'] ?? [];

        $errors = [];
        $resolved = [];
        foreach (self::LOCATIONS as $location) {
            $itemsInput = $menuInput[$location]['items'] ?? [];
            $resolved[$location] = $this->sanitizeMenuItems(
                is_array($itemsInput) ? $itemsInput : [],
                $errors,
                "menu.{$location}",
                $shop->id
            );
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/menus');
        }

        foreach (self::LOCATIONS as $location) {
            $menu = Menu::firstOrCreate([
                'shop_id' => $shop->id,
                'location' => $location,
            ]);

            MenuItem::where('menu_id', $menu->id)->delete();

            foreach ($resolved[$location] as $index => $item) {
                MenuItem::create([
                    'menu_id' => $menu->id,
                    'label' => $item['label'],
                    'type' => $item['type'],
                    'page_id' => $item['page_id'] ?? null,
                    'url' => $item['url'] ?? null,
                    'menu_order' => $index + 1,
                ]);
            }
        }

        $this->flashSet('success', 'Menus updated successfully.');

        return $this->redirect($response, '/admin/menus');
    }

    private function sanitizeMenuItems(array $itemsInput, array &$errors, string $fieldPrefix, int $shopId): array
    {
        $clean = [];
        foreach ($itemsInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string)($item['label'] ?? ''));
            $type = trim((string)($item['type'] ?? 'page'));
            $pageId = (int)($item['page_id'] ?? 0);
            $url = trim((string)($item['url'] ?? ''));

            if ($label === '') {
                $errors["{$fieldPrefix}.items.{$index}.label"] = 'Label is required.';
            } elseif (strlen($label) > 120) {
                $errors["{$fieldPrefix}.items.{$index}.label"] = 'Label is too long.';
            }

            if (!in_array($type, self::ITEM_TYPES, true)) {
                $errors["{$fieldPrefix}.items.{$index}.type"] = 'Select a valid item type.';
                continue;
            }

            if ($type === 'page') {
                if ($pageId <= 0) {
                    $errors["{$fieldPrefix}.items.{$index}.page_id"] = 'Select a page.';
                    continue;
                }
                $pageExists = Page::where('shop_id', $shopId)
                    ->where('type', 'standard')
                    ->where('id', $pageId)
                    ->exists();
                if (!$pageExists) {
                    $errors["{$fieldPrefix}.items.{$index}.page_id"] = 'Selected page is not available.';
                    continue;
                }
            }

            if ($type === 'url') {
                if ($url === '') {
                    $errors["{$fieldPrefix}.items.{$index}.url"] = 'URL is required.';
                    continue;
                }
                if (strlen($url) > 255) {
                    $errors["{$fieldPrefix}.items.{$index}.url"] = 'URL is too long.';
                    continue;
                }
            }

            $clean[] = [
                'label' => $label,
                'type' => $type,
                'page_id' => $type === 'page' ? $pageId : null,
                'url' => $type === 'url' ? $url : null,
            ];
        }

        return $clean;
    }

    private function getShopOrRedirect(Response $response): Shop|Response
    {
        $this->startSession();
        $shopId = (int)($_SESSION['shop_id'] ?? 0);
        if ($shopId <= 0) {
            $this->flashSet('error', 'Shop settings are not available for this account.');
            return $this->redirect($response, '/admin/dashboard');
        }

        $shop = Shop::find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        return $shop;
    }
}
