import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    email: string | null;
    action: string;
};

export default function Unsubscribe({ email, action }: Props) {
    return (
        <>
            <Head title="Unsubscribe" />

            <div className="flex min-h-screen items-center justify-center bg-background px-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Unsubscribe</CardTitle>
                        <CardDescription>
                            Stop receiving emails at{' '}
                            <span className="font-medium">
                                {email ?? 'this address'}
                            </span>
                            .
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={action} method="post">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Confirm unsubscribe
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
