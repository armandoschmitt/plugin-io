<?php //strict

namespace IO\Controllers;

use IO\Extensions\Constants\ShopUrls;
use IO\Helper\RouteConfig;
use IO\Services\CustomerService;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use IO\Guards\AuthGuard;
use Plenty\Modules\Category\Models\Category;
use Plenty\Modules\ShopBuilder\Helper\ShopBuilderRequest;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class CheckoutController
 * @package IO\Controllers
 */
class CheckoutController extends LayoutController
{
    use Loggable;

    /**
     * Prepare and render the data for the checkout
     *
     * @param Category $category
     *
     * @return string
     * @throws \ErrorException
     */
    public function showCheckout($category = null)
    {
        /** @var BasketItemRepositoryContract $basketItemRepository */
        $basketItemRepository = pluginApp(BasketItemRepositoryContract::class);

        /** @var SessionStorageRepositoryContract $sessionStorageRepository */
        $sessionStorageRepository = pluginApp(SessionStorageRepositoryContract::class);

        /** @var ContactRepositoryContract $contactRepository */
        $contactRepository = pluginApp(ContactRepositoryContract::class);

        /** @var ShopUrls $shopUrls */
        $shopUrls = pluginApp(ShopUrls::class);

        /** @var ShopBuilderRequest $shopBuilderRequest */
        $shopBuilderRequest = pluginApp(ShopBuilderRequest::class);
        $shopBuilderRequest->setMainContentType('checkout');

        if (!$shopBuilderRequest->isShopBuilder()) {
            if ($sessionStorageRepository->getSessionValue(SessionStorageRepositoryContract::GUEST_EMAIL) == null
                && $contactRepository->getContactId() <= 0) {
                $this->getLogger(__CLASS__)->info("IO::Debug.CheckoutController_notLoggedIn");
                AuthGuard::redirect(
                    $shopUrls->login,
                    ["backlink" => AuthGuard::getUrl()]
                );
            } elseif (!count($basketItemRepository->all())) {
                $this->getLogger(__CLASS__)->info("IO::Debug.CheckoutController_emptyBasket");
                AuthGuard::redirect($shopUrls->home, []);
            }
        } elseif (is_null($category)) {
            /** @var CategoryController $categoryController */
            $categoryController = pluginApp(CategoryController::class);
            return $categoryController->showCategory("checkout");
        }


        return $this->renderTemplate(
            "tpl.checkout",
            [
                'category' => $category
            ],
            false
        );
    }

    public function redirect()
    {
        if(!is_null($categoryByUrl = $this->checkForExistingCategory())) {
            return $categoryByUrl;
        }
        /** @var CategoryController $categoryController */
        $categoryController = pluginApp(CategoryController::class);
        return $categoryController->redirectRoute(RouteConfig::CHECKOUT);
    }
}
