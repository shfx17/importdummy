<?php

class AdminImportDummyController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent(): void
    {
        parent::initContent();

        $message = '';

        if (Tools::isSubmit('import_dummy_products')) {
            $message = $this->importProducts();
        }

        $this->context->smarty->assign([
            'message' => $message,
            'import_url' => $this->context->link->getAdminLink('AdminImportDummy'),
        ]);

        $this->setTemplate('importdummy.tpl');
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    protected function importProducts(): string
    {
        $json = file_get_contents('https://dummyjson.com/products');
        $data = json_decode($json, true);

        if (!isset($data['products'])) {
            return '<div class="alert alert-danger">Błąd pobierania danych z API.</div>';
        }

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($data['products'] as $product) {
            $isNew = $this->createOrUpdateProduct($product);

            if ($isNew) {
                $addedCount++;
            }

            if (!$isNew) {
                $skippedCount++;
            }
        }

        $message = '<div class="alert alert-success">';
        $message .= 'ADDED: ' . $addedCount . ' products. ';
        $message .= 'SKIPPED (updated): ' . $skippedCount . ' products.';
        $message .= '</div>';

        return $message;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function createOrUpdateProduct($productData): bool
    {
        $langId = Configuration::get('PS_LANG_DEFAULT');
        $sku = 'DUMMY-' . $productData['id'];

        $productId = (int) Product::getIdByReference($sku);
        $product = $productId ? new Product($productId) : new Product();

        $product->reference = $sku;
        $product->name = [$langId => $productData['title']];
        $product->description = [$langId => $productData['description']];
        $product->price = (float) $productData['price'];
        $product->id_category_default = 2;
        $product->link_rewrite = [$langId => Tools::str2url($productData['title'])];
        $product->active = 1;
        $quantity = isset($productData['stock']) ? (int) $productData['stock'] : 10;

        if ($productId) {
            $product->update();
            $isNew = false;

            $this->deleteProductImages($product->id);
        } else {
            if (!$product->add()) {
                return false;
            }

            $isNew = true;
        }

        if (!empty($productData['thumbnail'])) {
            $this->addProductImage($product->id, $productData['thumbnail']);
        }

        StockAvailable::setQuantity($product->id, 0, $quantity);

        return $isNew;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    protected function addProductImage($productId, $imageUrl): bool
    {
        $product = new Product($productId);
        $image = new Image();
        $image->id_product = $product->id;

        $langId = Configuration::get('PS_LANG_DEFAULT');
        $images = Image::getImages($langId, $product->id);
        $image->cover = empty($images) ? 1 : 0;

        if (!$image->add()) {
            return false;
        }

        $imagePath = $image->getPathForCreation();

        $tmpFile = tempnam(sys_get_temp_dir(), 'ps_img');
        if (file_put_contents($tmpFile, file_get_contents($imageUrl)) === false) {
            $image->delete();
            return false;
        }

        if (!ImageManager::resize($tmpFile, $imagePath . '.jpg')) {
            unlink($tmpFile);
            $image->delete();
            return false;
        }

        if (!unlink($tmpFile)) {
            return false;
        }

        if (!$image->save()) {
            return false;
        }

        if (!$product->update()) {
            return false;
        }

        $this->regenerateImageThumbnails($image->id);

        return true;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function regenerateImageThumbnails($imageId): bool
    {
        $image = new Image($imageId);
        $imagePath = $image->getPathForCreation();

        $originalFile = $imagePath . '.jpg';
        if (!file_exists($originalFile)) {
            return false;
        }

        $imageTypes = ImageType::getImagesTypes('products');
        foreach ($imageTypes as $imageType) {
            $width = (int)$imageType['width'];
            $height = (int)$imageType['height'];
            $resizedFile = $imagePath . '-' . stripslashes($imageType['name']) . '.jpg';
            ImageManager::resize($originalFile, $resizedFile, $width, $height);
        }

        return true;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    protected function deleteProductImages($productId): void
    {
        $images = Image::getImages(Configuration::get('PS_LANG_DEFAULT'), $productId);
        if (!empty($images)) {
            foreach ($images as $imageData) {
                $image = new Image($imageData['id_image']);
                $image->delete();
            }
        }
    }
}