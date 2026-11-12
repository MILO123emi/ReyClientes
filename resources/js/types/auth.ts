export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    can: {
        manageCards: boolean;
        manageUsers: boolean;
        viewAudit: boolean;
    };
};

export type Auth = {
    user: User;
};
