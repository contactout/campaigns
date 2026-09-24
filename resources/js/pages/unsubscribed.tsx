import { Head, Link } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export default function Unsubscribed() {
    return (
        <>
            <Head title="Unsubscribed" />

            <div className="flex min-h-screen items-center justify-center bg-background px-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>You're unsubscribed</CardTitle>
                        <CardDescription>
                            You will no longer receive emails from this sender.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="secondary" asChild>
                            <Link href="/">Back to home</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
