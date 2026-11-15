import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Flame,
    Plus,
    Search,
    UtensilsCrossed,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/RecipeController';
import CalorieRangeBadge from '@/components/calorie-range-badge';
import DietaryTagBadge from '@/components/dietary-tag-badge';
import MealTypeBadge from '@/components/meal-type-badge';
import VersionCoverage from '@/components/version-coverage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dietaryTagIcons } from '@/lib/dietary-tag';
import { mealTypeIcons } from '@/lib/meal-type';
import { cn } from '@/lib/utils';
import type {
    CalorieRangeOption,
    CalorieRangeValue,
    DietaryTagOption,
    DietaryTagValue,
    MealTypeOption,
    MealTypeValue,
    Paginated,
    Recipe,
} from '@/types';

type Filters = {
    search: string;
    meal_type: MealTypeValue | null;
    calorie_range: CalorieRangeValue | null;
    diets: DietaryTagValue[];
};

type Props = {
    recipes: Paginated<Recipe>;
    filters: Filters;
    calorieRanges: CalorieRangeOption[];
    mealTypes: MealTypeOption[];
    dietaryTags: DietaryTagOption[];
};

const ANY_CALORIES = 'any';

function applyFilters({ search, meal_type, calorie_range, diets }: Filters) {
    router.get(
        index.url(),
        {
            ...(search ? { search } : {}),
            ...(meal_type ? { meal_type } : {}),
            ...(calorie_range !== null ? { calorie_range } : {}),
            ...(diets.length > 0 ? { diets } : {}),
        },
        {
            preserveState: true,
            replace: true,
            only: ['recipes', 'filters'],
        },
    );
}

export default function RecipesIndex({
    recipes,
    filters,
    calorieRanges,
    mealTypes,
    dietaryTags,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    // The filters most recently applied, read by the debounced search so it
    // never re-sends a stale combination once another filter has changed.
    const appliedFilters = useRef(filters);

    useEffect(() => {
        appliedFilters.current = filters;
    }, [filters]);

    const update = (changes: Partial<Filters>) => {
        appliedFilters.current = {
            ...appliedFilters.current,
            search,
            ...changes,
        };
        applyFilters(appliedFilters.current);
    };

    const toggleDiet = (diet: DietaryTagValue) =>
        update({
            diets: filters.diets.includes(diet)
                ? filters.diets.filter((d) => d !== diet)
                : [...filters.diets, diet],
        });

    const filtering =
        filters.search !== '' ||
        filters.meal_type !== null ||
        filters.calorie_range !== null ||
        filters.diets.length > 0;

    useEffect(() => {
        // Only search when the box differs from what the server applied, so
        // remounts (pagination, strict mode) don't reset the page.
        if (search === filters.search) {
            return;
        }

        const timeout = setTimeout(() => {
            if (search !== appliedFilters.current.search) {
                update({ search });
            }
        }, 300);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <>
            <Head title="Recipes" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Recipes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {recipes.total}{' '}
                            {recipes.total === 1 ? 'recipe' : 'recipes'} · each
                            with up to {calorieRanges.length} calorie versions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <div className="relative flex-1 sm:w-64 sm:flex-none">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search recipes…"
                                aria-label="Search recipes"
                                className="pl-9"
                            />
                        </div>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                New recipe
                            </Link>
                        </Button>
                    </div>
                </header>

                <div
                    role="group"
                    aria-label="Filter by meal type"
                    className="-mt-2 flex gap-2 overflow-x-auto pb-1"
                >
                    <FilterChip
                        active={filters.meal_type === null}
                        onClick={() => update({ meal_type: null })}
                    >
                        All meals
                    </FilterChip>
                    {mealTypes.map((type) => {
                        const Icon = mealTypeIcons[type.value];

                        return (
                            <FilterChip
                                key={type.value}
                                active={filters.meal_type === type.value}
                                onClick={() =>
                                    update({
                                        meal_type:
                                            filters.meal_type === type.value
                                                ? null
                                                : type.value,
                                    })
                                }
                            >
                                <Icon className="size-3.5" />
                                {type.label}
                            </FilterChip>
                        );
                    })}
                </div>

                <div className="-mt-3 flex flex-wrap items-center gap-2">
                    <Select
                        value={
                            filters.calorie_range === null
                                ? ANY_CALORIES
                                : String(filters.calorie_range)
                        }
                        onValueChange={(value) =>
                            update({
                                calorie_range:
                                    value === ANY_CALORIES
                                        ? null
                                        : (Number(value) as CalorieRangeValue),
                            })
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            aria-label="Filter by calorie range"
                            className={cn(
                                'rounded-full',
                                filters.calorie_range !== null &&
                                    'border-primary text-foreground',
                            )}
                        >
                            <Flame className="size-3.5" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="start">
                            <SelectItem value={ANY_CALORIES}>
                                Any calories
                            </SelectItem>
                            {calorieRanges.map((range) => (
                                <SelectItem
                                    key={range.value}
                                    value={String(range.value)}
                                >
                                    {range.label} kcal
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <span
                        aria-hidden
                        className="mx-1 hidden h-5 w-px bg-border sm:block"
                    />

                    <div
                        role="group"
                        aria-label="Filter by diet"
                        className="flex flex-wrap gap-2"
                    >
                        {dietaryTags.map((tag) => {
                            const Icon = dietaryTagIcons[tag.value];

                            return (
                                <FilterChip
                                    key={tag.value}
                                    active={filters.diets.includes(tag.value)}
                                    onClick={() => toggleDiet(tag.value)}
                                >
                                    <Icon className="size-3.5" />
                                    {tag.label}
                                </FilterChip>
                            );
                        })}
                    </div>

                    {filtering && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto text-muted-foreground"
                            onClick={() => {
                                setSearch('');
                                update({
                                    search: '',
                                    meal_type: null,
                                    calorie_range: null,
                                    diets: [],
                                });
                            }}
                        >
                            <X />
                            Clear filters
                        </Button>
                    )}
                </div>

                {recipes.data.length === 0 ? (
                    <EmptyState searching={filtering} />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {recipes.data.map((recipe) => (
                            <li key={recipe.id}>
                                <RecipeCard
                                    recipe={recipe}
                                    ranges={calorieRanges}
                                />
                            </li>
                        ))}
                    </ul>
                )}

                {recipes.last_page > 1 && (
                    <nav
                        className="mt-auto flex items-center justify-between border-t pt-4 text-sm"
                        aria-label="Pagination"
                    >
                        <span className="text-muted-foreground tabular-nums">
                            {recipes.from}–{recipes.to} of {recipes.total}
                        </span>
                        <div className="flex gap-2">
                            <PageLink
                                href={recipes.prev_page_url}
                                label="Previous"
                            >
                                <ChevronLeft />
                            </PageLink>
                            <PageLink href={recipes.next_page_url} label="Next">
                                <ChevronRight />
                            </PageLink>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

function RecipeCard({
    recipe,
    ranges,
}: {
    recipe: Recipe;
    ranges: CalorieRangeOption[];
}) {
    const versions = recipe.versions ?? [];

    return (
        <Link
            href={show(recipe.id)}
            prefetch
            className="group flex h-full flex-col overflow-hidden rounded-xl border bg-card shadow-xs transition-all hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <div className="relative aspect-[16/10] overflow-hidden bg-muted">
                {recipe.image_url ? (
                    <img
                        src={recipe.image_url}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-muted to-muted-foreground/10">
                        <UtensilsCrossed className="size-8 text-muted-foreground/40" />
                    </div>
                )}
                <span className="absolute top-3 right-3 rounded-full bg-background/90 px-2 py-0.5 text-xs font-medium tabular-nums shadow-sm backdrop-blur">
                    {versions.length}/{ranges.length}
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="space-y-1">
                    <h2 className="line-clamp-1 font-medium">{recipe.name}</h2>
                    {(recipe.meal_types?.length ?? 0) +
                        (recipe.dietary_tags?.length ?? 0) >
                        0 && (
                        <div className="flex flex-wrap gap-1">
                            {recipe.meal_types?.map((m) => (
                                <MealTypeBadge key={m.id} value={m.meal_type} />
                            ))}
                            {recipe.dietary_tags?.map((t) => (
                                <DietaryTagBadge
                                    key={t.id}
                                    value={t.dietary_tag}
                                />
                            ))}
                        </div>
                    )}
                    <p className="line-clamp-2 min-h-10 text-sm text-muted-foreground">
                        {recipe.description || 'No description yet.'}
                    </p>
                </div>
                <div className="mt-auto space-y-2.5">
                    <VersionCoverage ranges={ranges} versions={versions} />
                    <div className="flex flex-wrap gap-1">
                        {versions.length === 0 ? (
                            <span className="text-xs text-muted-foreground">
                                No versions yet
                            </span>
                        ) : (
                            versions.map((version) => (
                                <CalorieRangeBadge
                                    key={version.id}
                                    value={version.calorie_range}
                                />
                            ))
                        )}
                    </div>
                </div>
            </div>
        </Link>
    );
}

function PageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: React.ReactNode;
}) {
    if (!href) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="icon" asChild>
            <Link href={href} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}

function EmptyState({ searching }: { searching: boolean }) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed px-6 py-20 text-center">
            <span className="flex size-12 items-center justify-center rounded-full bg-muted">
                <UtensilsCrossed className="size-5 text-muted-foreground" />
            </span>
            <h2 className="mt-4 font-medium">
                {searching ? 'No recipes match your filters' : 'No recipes yet'}
            </h2>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                {searching
                    ? 'Try a different name, meal type, calorie range or diet.'
                    : 'Create your first recipe, then add a version for each calorie range it supports.'}
            </p>
            {!searching && (
                <Button className="mt-6" asChild>
                    <Link href={create()}>
                        <Plus />
                        New recipe
                    </Link>
                </Button>
            )}
        </div>
    );
}

RecipesIndex.layout = {
    breadcrumbs: [{ title: 'Recipes', href: index() }],
};

function FilterChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-sm whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}
