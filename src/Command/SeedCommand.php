<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seed the database with demo data (idempotent-ish: clears and re-creates).')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['review', 'order_item', '`order`', 'cart_item', 'cart', 'product', 'category', '`user`'] as $table) {
            $conn->executeStatement('TRUNCATE TABLE '.$table);
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $categoriesData = [
            'Électronique' => 'electronique',
            'Vêtements' => 'vetements',
            'Maison & Jardin' => 'maison-jardin',
            'Sport & Loisirs' => 'sport-loisirs',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $slug) {
            $cat = new Category($name, $slug);
            $this->em->persist($cat);
            $categories[$slug] = $cat;
        }

        $productsData = [
            ['Casque audio sans fil', 'casque-audio-sans-fil', 'electronique', '79.99', 25],
            ['Smartphone Aurora X12', 'smartphone-aurora-x12', 'electronique', '499.00', 8],
            ['Enceinte Bluetooth portable', 'enceinte-bluetooth-portable', 'electronique', '39.90', 40],
            ['T-shirt coton bio', 't-shirt-coton-bio', 'vetements', '19.99', 100],
            ['Veste imperméable', 'veste-impermeable', 'vetements', '89.00', 15],
            ['Lampe de bureau LED', 'lampe-de-bureau-led', 'maison-jardin', '24.50', 30],
            ['Set de casseroles inox', 'set-de-casseroles-inox', 'maison-jardin', '129.00', 12],
            ['Tapis de course pliable', 'tapis-de-course-pliable', 'sport-loisirs', '349.00', 5],
            ['Ballon de football', 'ballon-de-football', 'sport-loisirs', '14.99', 3],
            ['Sac à dos de randonnée 40L', 'sac-a-dos-randonnee-40l', 'sport-loisirs', '59.90', 20],
        ];

        foreach ($productsData as [$name, $slug, $catSlug, $price, $stock]) {
            $product = new Product(
                $name,
                $slug,
                sprintf('Description détaillée pour %s. Produit de démonstration Rayan.store.', $name),
                $price,
                $stock,
                $categories[$catSlug]
            );
            // Illustration fournie avec le projet : public/images/products/<slug>.svg
            $product->setImagePath('products/'.$slug.'.svg');
            $this->em->persist($product);
        }

        $demoUsers = [
            ['demo@rayan.store', 'Demo', 'Client', 'password123', [User::ROLE_USER]],
            ['vendeur@rayan.store', 'Vendeur', 'Demo', 'password123', [User::ROLE_SELLER]],
            ['admin@rayan.store', 'Admin', 'Demo', 'password123', [User::ROLE_ADMIN]],
        ];

        foreach ($demoUsers as [$email, $first, $last, $plainPassword, $roles]) {
            $user = new User($email, $first, $last);
            $user->setRoles($roles);
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            $this->em->persist($user);
        }

        $this->em->flush();

        $output->writeln('<info>Seed terminé : 4 catégories, 10 produits, 3 utilisateurs (mot de passe: password123).</info>');

        return Command::SUCCESS;
    }
}
