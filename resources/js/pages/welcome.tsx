import { Head, Link, usePage } from '@inertiajs/react';
import { ChefHat } from 'lucide-react';
import { AppearanceToggle } from '@/components/appearance-toggle';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { index as recipesIndex } from '@/routes/admin/recipes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="flex items-center justify-end gap-2 p-4 md:p-6">
                    <AppearanceToggle />
                    {!auth.user && (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={register()}>Register</Link>
                            </Button>
                        </>
                    )}
                </header>

                <main className="flex flex-1 flex-col items-center justify-center px-6 pb-24 text-center">
                    <span className="flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
                        <ChefHat className="size-7" />
                    </span>
                    <h1 className="mt-6 text-3xl font-semibold tracking-tight md:text-4xl">
                        {name}
                    </h1>
                    <p className="mt-2 max-w-md text-muted-foreground">
                        Calorie-specific recipes, from under 200 to 800+ kcal,
                        with nutrition, ingredients and directions for every
                        version.
                    </p>
                    <Button size="lg" className="mt-8" asChild>
                        <Link href={auth.user ? recipesIndex() : login()}>
                            {auth.user
                                ? 'Open recipes'
                                : 'Log in to get started'}
                        </Link>
                    </Button>
                </main>
            </div>
        </>
    );
}
