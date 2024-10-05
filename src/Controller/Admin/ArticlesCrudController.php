<?php

namespace App\Controller\Admin;

use App\Entity\Articles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;

class ArticlesCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

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
            // Onglet : Informations principales
            FormField::addTab('Informations Principales'),
            IdField::new('id')->hideOnForm(),
            TextField::new('title')->setLabel('Titre'),
            SlugField::new('slug')->setLabel('Slug')->setTargetFieldName('title'),
            DateField::new('date')->setLabel('Date de publication'),
            DateField::new('modified')->setLabel('Date de modification')->hideOnIndex(),

            // Onglet : Contenu
            FormField::addTab('Contenu'),
            TextareaField::new('content')
                ->hideOnIndex()
                ->setLabel('Contenu')
                ->setNumOfRows(20)  // Optionnel : Définit la taille de l'éditeur
                ->setRequired(true),  // Définit si le champ est obligatoire ou non

            // Onglet : Catégories et Tags
            FormField::addTab('Catégories et Tags'),
            AssociationField::new('categories')
                ->hideOnIndex()
                ->setLabel('Catégories')
                ->formatValue(function ($value, $entity) {
                    // Assure-toi que $entity a bien une méthode getCategories() qui retourne une Collection
                    if (!$entity->getCategories()->isEmpty()) {
                        return implode(', ', $entity->getCategories()->map(function ($category) {
                            // Récupère le nom et le slug de chaque catégorie
                            return $category->getName();
                        })->toArray());
                    }

                    return ''; // Si aucune catégorie n'est liée, retourne une chaîne vide
                }),

            AssociationField::new('tags')
                ->hideOnIndex()
                ->setLabel('Tags')
                ->formatValue(function ($value, $entity) {
                    if (!$entity->getTags()->isEmpty()) {
                        return implode(', ', $entity->getTags()->map(function ($tag) {
                            return $tag->getName();
                        })->toArray());
                    }

                    return ''; // Retourne une chaîne vide si aucun tag n'est associé
                }),

            // Onglet : Médias
            FormField::addTab('Médias'),
            ImageField::new('firstMediaUrl')
                ->setBasePath('') // Chemin vers le dossier où sont stockées les images
                ->setLabel('Première Image')
                ->hideOnForm(),

            // Onglet : SEO
            FormField::addTab('SEO'),
            TextField::new('metaTitle')->setLabel('Meta Title')->hideOnIndex(),
            TextField::new('metaDescription')->setLabel('Meta Description')->hideOnIndex(),

            // Onglet : Statistiques et statut
            FormField::addTab('Statistiques et Statut'),
            NumberField::new('visit')->setLabel('Nombre de visites')->hideOnForm(),
            BooleanField::new('isOnline')->setLabel('En ligne'),
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

        if (null === $request) {
            return 'Articles (Total: N/A)';
        }

        
        // Récupérer le paramètre `isonline` de l'URL
        $isonline = $request->get('isOnline');

        // Compter les articles en tenant compte du paramètre `isonline`
        $criteria = [];
        if (null !== $isonline) {
            $criteria['isOnline'] = $isonline;
        }

        $articleCount = $this->entityManager->getRepository(Articles::class)->count($criteria);

        return "Articles (Total: $articleCount)";
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // Récupérer la requête courante
        $request = $this->requestStack->getCurrentRequest();
    
        // Construire la requête de base
        $queryBuilder = $this->entityManager->getRepository(Articles::class)->createQueryBuilder('a');
    
        // Vérifier si une requête existe avant de récupérer les paramètres de l'URL
        if (null !== $request) {
            // Récupérer le paramètre `isonline` de l'URL
            $isonline = $request->get('isOnline');
    
            // Ajouter la condition selon le paramètre `isonline`
            if (null !== $isonline) {
                $queryBuilder->andWhere('a.isOnline = :isonline')
                             ->setParameter('isonline', $isonline);
            }
        }
    
        // Laisser EasyAdmin gérer automatiquement les filtres. Pas besoin d'appliquer manuellement les filtres ici.
    
        // Appliquer les critères de recherche
        $searchTerms = $searchDto->getSearchMode();
        foreach ($fields as $field) {
            if ($field->isSortable()) {
                $queryBuilder->orWhere($queryBuilder->expr()->like('a.' . $field->getProperty(), ':searchTerm'))
                             ->setParameter('searchTerm', '%' . $searchTerms . '%');
            }
        }
    
        // Appliquer le tri par défaut sur l'ID du plus grand au plus petit
        $queryBuilder->orderBy('a.id', 'DESC');
    
        // Appliquer le tri de la recherche, s'il y en a
        if ($searchDto->getSort()) {
            foreach ($searchDto->getSort() as $field => $direction) {
                $queryBuilder->addOrderBy('a.' . $field, $direction);
            }
        }
    
        return $queryBuilder;
    }
    
    
    
}
