# Page Branches

Delegates editing of sections of a WordPress page tree to groups of volunteer editors, while administrators keep control of site structure.

## Language

**Branch group**:
A named set of members and branch roots.
_Avoid_: Team, section, permission group

**Branch root**:
A page assigned to a branch group as the top of a branch.
_Avoid_: Section page, entry page

**Branch**:
A branch root together with all of its descendant pages.
_Avoid_: Section, subtree

**Member**:
A user assigned to a branch group.
_Avoid_: Assignee

**Authorized page**:
For a given user, any page inside a branch of a branch group they are a member of.
_Avoid_: Allowed page, own page

**Restricted user**:
A member whose role does not already let them edit every page; their page editing is limited to their authorized pages.
_Avoid_: Branch editor (that is the role), scoped user

**Unrestricted user**:
A user whose role already lets them edit every page, such as an Administrator or Editor; membership never reduces their access.
_Avoid_: Privileged member

**Branch Editor**:
The role for volunteers whose only page access comes from membership.
_Avoid_: Volunteer editor, section editor

**Administrator**:
A user who manages site settings and branch groups.
_Avoid_: Admin user, site owner
