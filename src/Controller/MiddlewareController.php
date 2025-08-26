<?php

namespace App\Controller;

use Endertech\EcommerceMiddleware\Contracts\Model\ConfigurationInterface;
use Endertech\EcommerceMiddleware\Contracts\Repository\ConfigurationInitializerInterface;
use Endertech\EcommerceMiddleware\Core\Traits\Store\DoctrineAwareTrait;
use Endertech\EcommerceMiddlewareUserAdminBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class MiddlewareController extends AbstractController
{
    use DoctrineAwareTrait;

    #[Route('/', name: 'middleware_homepage')]
    public function index()
    {
        //$this->initializeData();

        return $this->redirectToRoute('sonata_admin_dashboard');
    }

    private function initializeData()
    {
        $flush = false;
        $em = $this->getDoctrine()->getManager();

        $userRepository = $em->getRepository(User::class);
        $userClass = $userRepository->getClassName();

        $existingUser = $userRepository->find(1);
        if (!$existingUser instanceof User) {
            /** @var User $adminUser */
            $adminUser = new $userClass();
            $adminUser->setUsername('admin');
            $adminUser->setEmail('admin@example.com');
            $adminUser->setPlainPassword('admin');
            $adminUser->setSuperAdmin(true);
            $adminUser->setEnabled(true);

            $em->persist($adminUser);
            $flush = true;
        }

        $configs = [];
        $configurationRepository = $em->getRepository(ConfigurationInterface::class);
        if ($configurationRepository instanceof ConfigurationInitializerInterface) {
            $configs = $configurationRepository->initializeConfiguration([
                //'log-record-rotation-days' => 10,
            ]);
        }
        foreach ($configs as $config) {
            $em->persist($config);
            $flush = true;
        }

        if ($flush) {
            $em->flush();
        }
    }
}
