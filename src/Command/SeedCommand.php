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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seed the database with demo data (idempotent-ish: clears and re-creates).')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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
            'Smartphones' => 'smartphones',
            'Ordinateurs' => 'ordinateurs',
            'Tablettes' => 'tablettes',
            'Audio' => 'audio',
            'Consoles' => 'consoles',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $slug) {
            $cat = new Category($name, $slug);
            $this->em->persist($cat);
            $categories[$slug] = $cat;
        }

        // [nom, slug, marque, catégorie, prix, prix de référence (barré) ou null, stock, description]
        $productsData = [
            ['iPhone 16 Pro', 'iphone-16-pro', 'Apple', 'smartphones', '1229.00', '1329.00', 25, 'Smartphone Apple haut de gamme, écran 6,3 pouces, triple appareil photo.'],
            ['Samsung Galaxy S25', 'samsung-galaxy-s25', 'Samsung', 'smartphones', '999.00', '1099.00', 30, 'Smartphone Android Samsung, écran AMOLED 6,2 pouces.'],
            ['Google Pixel 9', 'google-pixel-9', 'Google', 'smartphones', '799.00', null, 15, 'Smartphone Google avec Android pur et appareil photo avancé.'],
            ['MacBook Pro 14"', 'macbook-pro-14', 'Apple', 'ordinateurs', '2299.00', '2499.00', 8, 'Ordinateur portable Apple 14 pouces pour les usages professionnels.'],
            ['ASUS ROG Zephyrus G16', 'asus-rog-zephyrus-g16', 'ASUS', 'ordinateurs', '1999.00', null, 6, 'Ordinateur portable gaming 16 pouces.'],
            ['iPad Air 11"', 'ipad-air-11', 'Apple', 'tablettes', '699.00', '749.00', 20, 'Tablette Apple 11 pouces, compatible Apple Pencil.'],
            ['AirPods Pro 2', 'airpods-pro-2', 'Apple', 'audio', '259.00', '279.00', 40, 'Écouteurs sans fil à réduction de bruit active.'],
            ['Sony WH-1000XM5', 'sony-wh-1000xm5', 'Sony', 'audio', '349.00', '399.00', 18, 'Casque sans fil à réduction de bruit.'],
            ['PlayStation 5 Slim', 'playstation-5-slim', 'Sony', 'consoles', '449.99', '499.99', 10, 'Console de salon Sony, lecteur de disques inclus.'],
            ['Nintendo Switch OLED', 'nintendo-switch-oled', 'Nintendo', 'consoles', '319.99', null, 3, 'Console hybride salon et portable, écran OLED 7 pouces.'],
        ];

        foreach ($productsData as [$name, $slug, $brand, $catSlug, $price, $compareAtPrice, $stock, $description]) {
            $product = new Product($name, $slug, $description, $price, $stock, $categories[$catSlug]);
            $product->setBrand($brand);
            $product->setCompareAtPrice($compareAtPrice);
            $product->setImagePath($this->findProductImage($slug));
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

        $output->writeln('<info>Seed terminé : 5 catégories, 10 produits, 3 utilisateurs (mot de passe: password123).</info>');

        return Command::SUCCESS;
    }

    /**
     * Cherche une photo déposée dans public/images/products/ sous le nom du slug
     * (jpg, jpeg, png ou webp). À défaut, utilise l'illustration SVG fournie avec
     * le projet ; sans fichier du tout, le produit n'a pas d'image.
     */
    private function findProductImage(string $slug): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $extension) {
            $relative = sprintf('products/%s.%s', $slug, $extension);
            if (is_file($this->projectDir.'/public/images/'.$relative)) {
                return $relative;
            }
        }

        return null;
    }
}
