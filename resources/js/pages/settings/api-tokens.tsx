import { Transition } from '@headlessui/react';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index as apiTokensIndex } from '@/routes/api-tokens';
import type { BreadcrumbItem } from '@/types';

interface ApiToken {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
}

interface Props {
    tokens: ApiToken[];
    plainTextToken: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API tokens',
        href: apiTokensIndex().url,
    },
];

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }
    return new Date(value).toLocaleString();
}

export default function ApiTokens({ tokens, plainTextToken }: Props) {
    const [copied, setCopied] = useState(false);

    const copyToken = async () => {
        if (!plainTextToken) {
            return;
        }
        await navigator.clipboard.writeText(plainTextToken);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const deleteToken = (id: number) => {
        if (
            !confirm('Revoke this token? Any client using it will lose access.')
        ) {
            return;
        }
        router.delete(ApiTokenController.destroy.url({ tokenId: id }), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="API tokens"
                        description="Create tokens to authenticate the Chrome extension or other clients with the Linkerlee API."
                    />

                    {plainTextToken && (
                        <Alert>
                            <AlertTitle>Token created</AlertTitle>
                            <AlertDescription className="space-y-3">
                                <p>
                                    Copy this token now — it will not be shown
                                    again.
                                </p>
                                <div className="flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={plainTextToken}
                                        className="flex-1 font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={copyToken}
                                    >
                                        {copied ? 'Copied' : 'Copy'}
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Form
                        {...ApiTokenController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-4"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Token name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="e.g. Chrome extension"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>
                                        Create token
                                    </Button>
                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Created
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <Separator />

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Existing tokens"
                        description="Revoke any token you no longer use."
                    />

                    {tokens.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            You have no tokens yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {tokens.map((token) => (
                                <li
                                    key={token.id}
                                    className="flex items-center justify-between gap-4 p-4"
                                >
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {token.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Created{' '}
                                            {formatDate(token.created_at)}
                                            {' · '}
                                            Last used{' '}
                                            {formatDate(token.last_used_at)}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => deleteToken(token.id)}
                                    >
                                        Revoke
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
