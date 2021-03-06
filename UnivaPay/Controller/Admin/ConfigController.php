<?php
namespace Plugin\UnivaPay\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\UnivaPay\Form\Type\Admin\ConfigType;
use Plugin\UnivaPay\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class ConfigController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * ConfigController constructor.
     *
     * @param ConfigRepository $configRepository
     */
    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/univapay/config", name="univa_pay_admin_config")
     * @Template("@UnivaPay/admin/config.twig")
     */
    public function index(Request $request)
    {
        $Config = $this->configRepository->get();
        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $Config = $form->getData();
            $this->entityManager->persist($Config);
            $this->entityManager->flush($Config);

            $this->addSuccess('univa_pay.admin.save.success', 'admin');

            return $this->redirectToRoute('univa_pay_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
