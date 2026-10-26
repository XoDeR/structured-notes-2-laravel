export type TreeNode = {
    id: string;
    parent_id: string | null;
    title: string;
    is_public: boolean;
    can_edit: boolean;
};

export type TopicSummary = {
    id: string;
    title: string;
    is_public: boolean;
    is_link: boolean;
    is_owner: boolean;
    is_favorite: boolean;
};

export type Attachment = {
    id: string;
    name: string;
    size: number;
    url: string;
};

export type TopicDetail = {
    id: string;
    title: string;
    content: string;
    is_public: boolean;
    is_link: boolean;
    can_edit: boolean;
    owner: string;
    updated_at: string;
    share_url: string | null;
    tags: string[];
    attachments: Attachment[];
};

export type SearchResult = {
    topic_id: string;
    node_id: string;
    title: string;
    path: string;
};

export type SearchState = {
    q: string;
    tag: string;
    fav: boolean;
    mode: 'phrase' | 'all';
    title_only: boolean;
    results: SearchResult[];
};

export type Clipboard =
    | { kind: 'node'; id: string; title: string }
    | {
          kind: 'topic';
          id: string;
          title: string;
          mode: 'move' | 'copy' | 'link';
          fromNodeId: string;
      };
