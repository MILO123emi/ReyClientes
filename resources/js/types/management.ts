export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    type: string;
    is_owner: boolean;
    can_manage_cards: boolean;
    last_login_at: string | null;
};

export type AuditEntry = {
    created_at: string;
    user: string;
    action_label: string;
    description: string;
    ip_address: string | null;
};
