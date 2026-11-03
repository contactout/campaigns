import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { index as connectionsIndex } from '@/routes/mailer-connections';
import type { MailerConnectionOption } from '@/types';

export default function ConnectionWarning({
    connection,
}: {
    connection: MailerConnectionOption | null;
}) {
    const { currentTeam } = usePage().props;

    if (!connection || connection.display_status === 'Verified') {
        return null;
    }

    return (
        <Alert>
            <AlertTriangle />
            <AlertTitle>Connection needs attention</AlertTitle>
            <AlertDescription>
                <p>
                    {connection.name} is {connection.display_status_label}.
                    Sending will wait until this connection is verified.
                </p>
                <Link
                    href={connectionsIndex(currentTeam?.slug ?? '')}
                    className="underline underline-offset-4"
                >
                    Go to Connections to verify or reconnect
                </Link>
            </AlertDescription>
        </Alert>
    );
}
