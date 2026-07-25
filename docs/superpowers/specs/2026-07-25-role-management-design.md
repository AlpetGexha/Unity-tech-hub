# Role Management Design

## Goal

Turn the existing role form into a role-management flow where HR users can see previously created roles in a table, create a new role, and edit an existing role. Roles can be edited but not deleted, so candidates and analyses remain attached to their role.

## User experience

- `GET /hr/jobs` renders a roles table.
- The table shows each role's title, short requirements and priorities summaries, and creation date.
- A `Create role` action opens the existing role form on a dedicated create page.
- Each table row has an `Edit` action that opens the same form prefilled with that role's current values.
- Successful create and update operations return to the roles table.
- Validation errors stay on the relevant form and display beside the corresponding field.
- No delete action is added.

## Server architecture

Use resource-style routes and implicit model binding:

- `GET /hr/jobs` → `JobController@index`
- `GET /hr/jobs/create` → `JobController@create`
- `POST /hr/jobs` → `JobController@store`
- `GET /hr/jobs/{job}/edit` → `JobController@edit`
- `PUT/PATCH /hr/jobs/{job}` → `JobController@update`

The index passes the roles needed by the table as an Inertia prop. Create renders an empty form, while edit passes the selected `JobOpening`. Store and update use a shared form request so title, requirements, and priorities have the same validation rules. Update changes only the role fields and does not alter related candidates, analyses, or relationships.

The existing single-candidate flow continues to use the selected role through the existing data model. The current post-create redirect to the candidate form is changed to the roles table so role management remains the primary workflow; the candidate form can still be opened through the existing navigation.

## Frontend structure

- Replace `resources/js/pages/hr/jobs.tsx` with the roles table page.
- Add a reusable role form component if the existing conventions support sharing the create and edit fields without duplicating markup.
- Add create and edit page components under `resources/js/pages/hr/`.
- Use Inertia `Link` navigation and the generated Wayfinder route/controller helpers already used by the application.
- Keep the current Tailwind and UI component conventions, including inline validation errors, processing states, and accessible labels.

## Error handling and safety

- Require a non-empty title, requirements, and priorities using the existing validation rules.
- Use implicit route model binding for edit and update operations.
- Do not add destructive role operations.
- Preserve existing role relationships and analysis behavior after edits.

## Tests

Extend the existing role feature coverage to verify:

1. The index renders and exposes existing roles to the Inertia page.
2. The create page renders an empty role form.
3. The edit page renders the selected role's current values.
4. A valid role update persists all editable fields and redirects to the index.
5. An update for a missing or invalid role is rejected by route binding.
6. Store and update validation reject missing required fields.
7. Existing role creation and rubric behavior continue to pass.

Run the focused PHPUnit test file, Pint for changed PHP files, and the frontend lint/build checks appropriate to the repository before completion.

## Scope boundaries

This change does not add role deletion, role duplication, search/filtering, pagination, permissions, candidate migration between roles, or changes to the role rubric algorithm.
