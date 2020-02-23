<?php

namespace App\Controller\Modules\Job;

use App\Controller\Utils\AjaxResponse;
use App\Controller\Utils\Application;
use App\Controller\Utils\Repositories;
use App\Services\Exceptions\ExceptionDuplicatedTranslationKey;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Annotation\Route;

class MyJobHolidaysController extends AbstractController
{

    const KEY_CHOICES = 'choices';

    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
     * @Route("/my-job/holidays", name="my-job-holidays")
     * @param Request $request
     * @return Response
     */
    public function display(Request $request) {

        $this->add($request);

        if (!$request->isXmlHttpRequest()) {
            return $this->renderTemplate(false);
        }

        $template_content  = $this->renderTemplate(true)->getContent();
        return AjaxResponse::buildResponseForAjaxCall(200, "", $template_content);
    }

    /**
     * @param bool $ajax_render
     * @return Response
     */
    private function renderTemplate($ajax_render) {

        $all_pools_years                    = $this->app->repositories->myJobHolidaysPoolRepository->getAllPoolsYears();
        $all_holidays_spent                 = $this->app->repositories->myJobHolidaysRepository->findBy(['deleted' => 0]);
        $job_holidays_summary               = $this->app->repositories->myJobHolidaysPoolRepository->getHolidaysSummaryGroupedByYears();
        $job_holidays_available_totally     = $this->app->repositories->myJobHolidaysPoolRepository->getAvailableDaysTotally();

        $job_holidays_form  = $this->app->forms->jobHolidaysForm([
            static::KEY_CHOICES => $all_pools_years
        ]);

        $twig_data = [
            'ajax_render'                       => $ajax_render,
            'all_holidays_spent'                => $all_holidays_spent,
            'job_holidays_form'                 => $job_holidays_form->createView(),
            'job_holidays_summary'              => $job_holidays_summary,
            'job_holidays_available_totally'    => $job_holidays_available_totally
        ];

        return $this->render('modules/my-job/holidays.html.twig', $twig_data);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function add(Request $request): void {

        $all_pools_years = $this->app->repositories->myJobHolidaysPoolRepository->getAllPoolsYears();

        $form = $this->app->forms->jobHolidaysForm([
            static::KEY_CHOICES => $all_pools_years
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($form->getData());
            $em->flush();
        }
    }

    /**
     * @Route("/my-job/holidays/update/",name="my-job-holidays-update")
     * @param Request $request
     * @return JsonResponse
     * @throws ExceptionDuplicatedTranslationKey
     */
    public function update(Request $request) {
        $parameters = $request->request->all();
        $entity     = $this->app->repositories->myJobHolidaysRepository->find($parameters['id']);
        $response   = $this->app->repositories->update($parameters, $entity);

        return $response;

    }

    /**
     * @Route("/my-job/holidays/remove/",name="my-job-holidays-remove")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function remove(Request $request) {

        $response = $this->app->repositories->deleteById(
            Repositories::MY_JOB_HOLIDAYS_REPOSITORY_NAME,
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
}
