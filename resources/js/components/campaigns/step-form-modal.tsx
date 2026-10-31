import StepForm from '@/components/campaigns/step-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    CampaignSignatureOption,
    CampaignStep,
    CampaignTemplateOption,
    MergePlaceholder,
} from '@/types';

type Props = {
    campaignId: number;
    step?: CampaignStep | null;
    templates: CampaignTemplateOption[];
    signatures: CampaignSignatureOption[];
    placeholders: MergePlaceholder[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Dialog wrapper around the shared step form.
 *
 * The campaign detail page uses the two-pane editor instead, but the modal stays
 * available for any screen that wants to edit a step in a dialog.
 */
export default function StepFormModal({
    campaignId,
    step,
    templates,
    signatures,
    placeholders,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                {open ? (
                    <StepForm
                        key={step?.id ?? 'new'}
                        campaignId={campaignId}
                        step={step}
                        templates={templates}
                        signatures={signatures}
                        placeholders={placeholders}
                        onSuccess={() => onOpenChange(false)}
                        formClassName="flex flex-col gap-6"
                    >
                        {({ processing, fields }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {step ? 'Edit step' : 'New step'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Email steps run in order.
                                    </DialogDescription>
                                </DialogHeader>

                                {fields}

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button type="submit" disabled={processing}>
                                        {step ? 'Save step' : 'Add step'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </StepForm>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
