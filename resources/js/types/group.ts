export interface TagRef {
    id: number;
    name: string;
}

/**
 * The tag rules that decide which links a collection gathers on its own, on top
 * of the ones added to it by hand. A link is gathered when it carries every
 * `andTags` tag and at least one `orTags` tag; anything carrying a `notTags` tag
 * is dropped from the collection whether it was gathered or added by hand.
 */
export interface GroupRules {
    andTags: TagRef[];
    orTags: TagRef[];
    notTags: TagRef[];
}

/** A collection as the parent picker and the listing need it. */
export interface GroupSummary {
    id: number;
    title: string;
    parentGroupId: number | null;
}

export type GroupWithRules = GroupSummary & GroupRules;

export interface GroupListItem extends GroupWithRules {
    linksCount: number;
    childGroupsCount: number;
}
