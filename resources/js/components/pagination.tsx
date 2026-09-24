import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

type Props = {
    links: PaginationLink[];
};

const previousLabels = ['&laquo; Previous', 'Previous'];
const nextLabels = ['Next &raquo;', 'Next'];

export default function Pagination({ links }: Props) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-1">
            {links.map((link, index) => {
                const isPrevious = previousLabels.includes(link.label);
                const isNext = nextLabels.includes(link.label);
                const label = isPrevious
                    ? 'Previous'
                    : isNext
                      ? 'Next'
                      : link.label;

                if (link.url === null) {
                    return (
                        <Button
                            key={`${link.label}-${index}`}
                            variant="ghost"
                            size="sm"
                            disabled
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    );
                }

                return (
                    <Button
                        key={`${link.label}-${index}`}
                        variant={link.active ? 'default' : 'ghost'}
                        size="sm"
                        asChild
                    >
                        <Link
                            href={link.url}
                            preserveScroll
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    </Button>
                );
            })}
        </div>
    );
}
