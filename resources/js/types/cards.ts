export type PhotoUrls = { thumb: string; full: string };

export type AuthorizedPerson = {
    id: number;
    client_id: number;
    full_name: string;
    dni: string;
    phone: string;
    relationship: string;
    relationship_label: string;
    relationship_other: string | null;
    photo: PhotoUrls;
};

export type Client = {
    id: number;
    full_name: string;
    dni: string;
    phone: string;
    photo: PhotoUrls;
    people_count: number;
    authorized_people: AuthorizedPerson[];
};

export type RelationshipOption = { value: string; label: string };

export type Paginated<T> = {
    data: T[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
};

export type SearchRole = 'todos' | 'duenos' | 'autorizados';
export type SearchCounts = { owners: number; authorized: number };
export type SearchResult = {
    key: string;
    role: 'owner' | 'authorized';
    match: 'exact' | 'prefix' | 'partial';
    client: Client;
    person: AuthorizedPerson | null;
};
export type ClientFilters = {
    q: string;
    dni: { masked: string } | null;
    role: SearchRole;
};
