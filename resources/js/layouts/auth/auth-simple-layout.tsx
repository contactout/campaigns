import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-b from-muted/60 via-background to-background p-5 sm:p-8">
            <div className="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-10">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 rounded-md font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <div className="mb-1 flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-7" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
