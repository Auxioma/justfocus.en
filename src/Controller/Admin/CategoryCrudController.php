<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('Name'),
            BooleanField::new('isOnline'),
            IntegerField::new('articleCount', 'Number of Articles')
                ->formatValue(function ($value, $entity) {
                    return $entity->getArticleCount();
                }),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere('entity.parent IS NULL');

        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        $showSubCategories = Action::new('showSubCategories', 'Voir les sous-catégories')
            ->linkToCrudAction('showSubCategories') // Action dans le contrôleur
            ->setCssClass('btn btn-info'); // Optionnel : pour styliser le bouton

        return $actions
            ->add(Action::INDEX, $showSubCategories)
            ->disable(Action::NEW, Action::DELETE, Action::EDIT);
    }

    public function showSubCategories(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $category = $context->getEntity()->getInstance();

        // Récupérer les sous-catégories à partir du repository
        $subCategories = $entityManager->getRepository(Category::class)
            ->findBy(['parent' => $category]);

        // Afficher les sous-catégories dans EasyAdmin
        return $this->render('@EasyAdmin/page/category_subcategories.html.twig', [
            'category' => $category,
            'subCategories' => $subCategories,
        ]);
    }
}
