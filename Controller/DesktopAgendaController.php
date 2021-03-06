<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\AgendaBundle\Controller;

use Claroline\AgendaBundle\Entity\Event;
use Claroline\AgendaBundle\Form\ImportAgendaType;
use Claroline\AgendaBundle\Manager\AgendaManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class DesktopAgendaController extends Controller
{
    private $tokenStorage;
    private $om;
    private $request;
    private $translator;
    private $agendaManager;
    private $router;

    /**
     * @DI\InjectParams({
     *     "tokenStorage"       = @DI\Inject("security.token_storage"),
     *     "om"                 = @DI\Inject("claroline.persistence.object_manager"),
     *     "request"            = @DI\Inject("request"),
     *     "translator"         = @DI\Inject("translator"),
     *     "agendaManager"      = @DI\Inject("claroline.manager.agenda_manager"),
     *     "router"             = @DI\Inject("router")
     * })
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        ObjectManager $om,
        Request $request,
        TranslatorInterface $translator,
        AgendaManager $agendaManager,
        RouterInterface $router
    )
    {
        $this->tokenStorage = $tokenStorage;
        $this->om = $om;
        $this->request = $request;
        $this->translator = $translator;
        $this->agendaManager = $agendaManager;
        $this->router = $router;
    }
    /**
     * @EXT\Route(
     *     "/show",
     *     name="claro_desktop_agenda_show",
     *     options = {"expose"=true}
     * )
     */
    public function desktopShowAction()
    {
        $data = $this->agendaManager->desktopEvents($this->tokenStorage->getToken()->getUser());

        return new JsonResponse($data);
    }

    /**
     * @EXT\Route(
     *     "/add/event/form",
     *     name="claro_desktop_agenda_add_event_form",
     *     options = {"expose"=true}
     * )
     * @EXT\Template("ClarolineAgendaBundle:Agenda:addEventModalForm.html.twig")
     *
     * @return array
     */
    public function addEventModalFormAction()
    {
        $formType = $this->get('claroline.form.agenda');
        $form = $this->createForm($formType, new Event());

        return array(
            'form' => $form->createView(),
            'action' => $this->router->generate('claro_desktop_agenda_add')
        );
    }

    /**
     * @EXT\Route("/add", name="claro_desktop_agenda_add")
     * @EXT\Template("ClarolineAgendaBundle:Agenda:addEventModalForm.html.twig")
     */
    public function addEvent()
    {
        $formType = $this->get('claroline.form.agenda');
        $form = $this->createForm($formType, new Event());
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $event = $form->getData();
            $data = $this->agendaManager->addEvent($event, null);

            return new JsonResponse(array($data), 200);
        }

        return array(
            'form' => $form->createView(),
            'action' => $this->router->generate('claro_desktop_agenda_add', array())
        );
    }

    /**
     * @EXT\Route("/widget/{order}", name="claro_desktop_agenda_widget")
     * @EXT\Template("ClarolineAgendaBundle:Widget:agenda_widget.html.twig")
     */
    public function widgetAction($order = null)
    {
        $em = $this-> get('doctrine.orm.entity_manager');
        $usr = $this->tokenStorage->getToken()->getUser();
        $listEventsDesktop = $em->getRepository('ClarolineAgendaBundle:Event')->findDesktop($usr, false);
        $listEvents = $em->getRepository('ClarolineAgendaBundle:Event')->findByUserWithoutAllDay($usr, 5, $order);

        return array('listEvents' => array_merge($listEvents, $listEventsDesktop));
    }


    /**
     * @EXT\Route(
     *     "/import/modal/form",
     *     name="claro_agenda_import_form",
     *     options = {"expose"=true}
     * )
     * @EXT\Template("ClarolineAgendaBundle:Tool:importIcsModalForm.html.twig")
     *
     * @return array
     */
    public function importEventsModalForm()
    {
        $form = $this->createForm(new ImportAgendaType());

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route("/import", name="claro_agenda_import")
     * @EXT\Template("ClarolineAgendaBundle:Tool:importIcsModalForm.html.twig")
     *
     * @return array
     */
    public function importsEventsIcsAction()
    {
        $form = $this->createForm(new ImportAgendaType());
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $events = $this->agendaManager->importEvents($form->get('file')->getData());

            return new JsonResponse($events, 200);
        }

        return array('form' => $form->createView());
    }
}
