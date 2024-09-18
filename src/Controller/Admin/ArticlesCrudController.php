<?php

namespace App\Controller\Admin;

use App\Entity\Articles;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;

class ArticlesCrudController extends AbstractCrudController
{
    private $entityManager;
    private $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    public static function getEntityFqcn(): string
    {
        return Articles::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title')->setMaxLength(255),
            DateField::new('date'),
            NumberField::new('visit'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, $this->getPageTitle());
    }

    private function getPageTitle(): string
    {
        // Récupérer la requête courante
        $request = $this->requestStack->getCurrentRequest();
        // Récupérer le paramètre `isonline` de l'URL
        $isonline = $request->get('isOnline');
    
        // Compter les articles en tenant compte du paramètre `isonline`
        $criteria = [];
        if ($isonline !== null) {
            $criteria['isOnline'] = $isonline;
        }
    
        $articleCount = $this->entityManager->getRepository(Articles::class)->count($criteria);
    
        return "Articles (Total: $articleCount)";
    }
    

    public function configureActions(Actions $actions): Actions
    {
        // Récupérer la requête courante
        $request = $this->requestStack->getCurrentRequest();
        $isonline = $request->get('isOnline');
    
        // Désactiver certaines actions si `isOnline` est à 0
        if ($isonline == 0) {
            return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE);
        }
    
        return $actions;
    }
    

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // Récupérer la requête courante
        $request = $this->requestStack->getCurrentRequest();
        // Récupérer le paramètre `isonline` de l'URL
        $isonline = $request->get('isOnline');

        // Construire la requête de base
        $queryBuilder = $this->entityManager->getRepository(Articles::class)->createQueryBuilder('a');
        
        // Ajouter la condition selon le paramètre `isonline`
        if ($isonline !== null) {
            $queryBuilder->andWhere('a.isOnline = :isonline')
                         ->setParameter('isonline', $isonline);
        }

        // Ajouter les filtres de recherche EasyAdmin
        foreach ($filters as $filter) {
            $filter->apply($queryBuilder);
        }

        // Appliquer les critères de recherche
        $searchTerms = $searchDto->getSearchMode();
        foreach ($fields as $field) {
            if ($field->isSortable()) {
                $queryBuilder->orWhere($queryBuilder->expr()->like('a.' . $field->getProperty(), ':searchTerm'))
                             ->setParameter('searchTerm', '%' . $searchTerms . '%');
            }
        }

        // Appliquer le tri
        if ($searchDto->getSort()) {
            foreach ($searchDto->getSort() as $field => $direction) {
                $queryBuilder->addOrderBy('a.' . $field, $direction);
            }
        }

        return $queryBuilder;
    }
}
