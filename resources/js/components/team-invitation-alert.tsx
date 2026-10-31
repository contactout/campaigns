import type { ReactNode } from 'react';
import { InfoIcon } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props =
    | {
          invitation: TeamInvitationContext;
          action: 'Log in' | 'Register';
      }
    | {
          message: ReactNode;
          actionSlot: ReactNode;
      };

export default function TeamInvitationAlert(props: Props) {
    if ('invitation' in props) {
        return (
            <Alert
                data-test="team-invitation-alert"
                className="border-accent bg-banner text-primary [&>svg]:text-primary"
            >
                <InfoIcon />
                <AlertDescription className="text-primary">
                    {props.action} to join the "{props.invitation.teamName}"
                    team.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert
            data-test="team-invitation-alert"
            className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 border-accent bg-banner px-4 py-2.5 text-primary"
        >
            <div className="min-w-0 text-[13px] leading-5 font-medium text-primary">
                {props.message}
            </div>
            {props.actionSlot}
        </Alert>
    );
}
