<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Cabin;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Override;

/**
 * @extends AbstractCrudController<\App\Entity\Cabin>
 */
final class CabinCrudController extends AbstractCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Cabin::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Cabin')
            ->setEntityLabelInPlural('Cabins')
            ->setSearchFields(['id', 'row'])
            ->setDefaultSort(['row' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('row'))
            ->add(NumericFilter::new('beds'))
            ->add(BooleanFilter::new('isFree'));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield IntegerField::new('beds', 'Number of beds');
        yield IntegerField::new('row', 'Rows');
        yield ArrayField::new('amenities', 'amenities');
        yield BooleanField::new('isFree', 'Free');
    }
}
