import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-8 shrink-0 items-center justify-center rounded-[10px] bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-[18px] text-sidebar-primary-foreground" />
            </div>
            <div className="grid min-w-0 flex-1 text-left">
                <span className="truncate text-[14px] leading-tight font-bold tracking-[-0.01em]">
                    Campaigns OS
                </span>
            </div>
        </>
    );
}
