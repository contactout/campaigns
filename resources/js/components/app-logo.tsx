import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-6 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-3.5 text-sidebar-primary-foreground" />
            </div>
            <div className="grid min-w-0 flex-1 text-left">
                <span className="truncate text-body leading-tight font-semibold tracking-[-0.01em]">
                    Campaigns OS
                </span>
            </div>
        </>
    );
}
