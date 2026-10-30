# ADR 0002: Store groups in one option

## Status

Accepted

## Context

The product needs group names, branch roots, and members to be managed as one configuration at club scale. Separate storage would fragment that state.

## Decision

Store all config in the autoloaded WordPress option `jp_permissions_groups`, schema 1: `['schema'=>1, 'next_id'=>3, 'groups'=>[1=>['name'=>'Football Editors','roots'=>[12],'members'=>[5,9]]]]`. IDs are integers allocated from `next_id` and never reused. `GroupRepository` normalizes reads: missing or non-array becomes empty config; malformed groups are dropped; IDs are cast with `absint`, with zeroes and duplicates removed.

## Consequences

A single write keeps group roots and membership together; concurrent administrator edits use documented last-write-wins behavior. Roles were rejected because they are global capability bundles and the multi-role UI is absent; user meta would split membership from roots into two writes; custom tables are unnecessary at club scale.
