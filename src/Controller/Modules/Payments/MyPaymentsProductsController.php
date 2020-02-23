<?php

namespace App\Controller\Modules\Payments;

use App\Controller\Utils\AjaxResponse;
use App\Controller\Utils\Application;
use App\Controller\Utils\Repositories;
use App\Entity\Modules\Payments\MyPaymentsProduct;
use App\Form\Modules\Payments\MyPaymentsProductsType;
use App\Services\Exceptions\ExceptionDuplicatedTranslationKey;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MyPaymentsProductsController extends AbstractController {

    const PRICE_COLUMN_NAME = 'price';

    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
     * @Route("/my-payments-products", name="my-payments-products")
     * @param Request $request
     * @return Response
     * @throws ExceptionDuplicatedTranslationKey
     */
    public function display(Request $request) {
        $this->addFormDataToDB($request);

        if (!$request->isXmlHttpRequest()) {
            return $this->renderTemplate(false);
        }

        $template_content  = $this->renderTemplate(true)->getContent();
        return AjaxResponse::buildResponseForAjaxCall(200, "", $template_content);
    }

    /**
     * @param bool $ajax_render
     * @return Response
     * @throws ExceptionDuplicatedTranslationKey
     */
    protected function renderTemplate($ajax_render = false) {
        $form               = $this->getForm();
        $products_form_view = $form->createView();

        $column_names           = $this->getDoctrine()->getManager()->getClassMetadata(MyPaymentsProduct::class)->getColumnNames();
        $column_names           = $this->reorderPriceColumn($column_names);
        Repositories::removeHelperColumnsFromView($column_names);

        $products_all_data      = $this->app->repositories->myPaymentsProductRepository->findBy(['deleted' => 0]);
        $currency_multiplier    = $this->app->repositories->myPaymentsSettingsRepository->fetchCurrencyMultiplier();

        return $this->render('modules/my-payments/products.html.twig',
            compact('column_names', 'products_all_data', 'products_form_view', 'currency_multiplier', 'ajax_render')
        );
    }

    /**
     * @param $request
     */
    protected function addFormDataToDB($request) {
        $products_form = $this->getForm();
        $products_form->handleRequest($request);

        if ($products_form->isSubmitted() && $products_form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($products_form->getData());
            $em->flush();
        }

    }

    /**
     * @Route("/my-payments-products/remove/", name="my-payments-products-remove")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function remove(Request $request) {
        $response = $this->app->repositories->deleteById(
            Repositories::MY_PAYMENTS_PRODUCTS_REPOSITORY_NAME,
            $request->request->get('id')
        );

        $message = $response->getContent();

        if ($response->getStatusCode() == 200) {
            $rendered_template = $this->renderTemplate(true);
            $template_content  = $rendered_template->getContent();

            return AjaxResponse::buildResponseForAjaxCall(200, $message, $template_content);
        }
        return AjaxResponse::buildResponseForAjaxCall(500, $message);
    }

    /**
     * @Route("my-payments-products/update/",name="my-payments-products-update")
     * @param Request $request
     * @return JsonResponse
     * @throws ExceptionDuplicatedTranslationKey
     */
    public function UpdateDataInDB(Request $request) {
        $parameters = $request->request->all();
        $entity     = $this->app->repositories->myPaymentsProductRepository->find($parameters['id']);
        $response   = $this->app->repositories->update($parameters, $entity);

        return $response;
    }

    /**
     * Todo: check later why is this done this strange way....
     * @param $column_names
     * @return array
     * @throws ExceptionDuplicatedTranslationKey
     */
    private function reorderPriceColumn($column_names) {
        $price_key = array_search(static::PRICE_COLUMN_NAME, $column_names);

        if (!array_key_exists(static::PRICE_COLUMN_NAME, $column_names)) {
            $message = $this->app->translator->translate('exceptions.MyPaymentsProductsController.keyPriceNotFoundInProductsColumnsArray');
            new Exception($message);
        }

        unset($column_names[$price_key]);
        $column_names[] = static::PRICE_COLUMN_NAME;

        return $column_names;
    }

    private function getForm() {
        return $this->createForm(MyPaymentsProductsType::class);
    }
}
