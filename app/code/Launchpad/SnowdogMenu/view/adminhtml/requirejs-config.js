/**
 * Launchpad Snowdog Menu — admin Vue overrides (TASK-08343C / FEAT-ZNJ4KF).
 *
 * Overrides the editor bootstrap module alias via `paths` (reliable for
 * plain module IDs, unlike `map` on `vue!`-plugin resources which was
 * verified not to apply). `Launchpad_SnowdogMenu/js/nodes` is a copy of
 * the vendor `Snowdog_Menu/js/nodes` with ONE change: the menu-type
 * dependency points at `vue!Launchpad_SnowdogMenu/vue/menu-type` (the
 * extended form with the per-node banner fields). Re-baseline the copy
 * when the vendor entry changes.
 */
var config = {
    paths: {
        'menuNodes': 'Launchpad_SnowdogMenu/js/nodes'
    }
};
