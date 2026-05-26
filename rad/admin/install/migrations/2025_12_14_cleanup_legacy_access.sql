-- Cleanup legacy access columns and app linkages now superseded by permission bindings and navset_role.
-- Safe on MySQL 8+ (DROP COLUMN IF EXISTS). Review before running in production.

-- Remove legacy CSV-based access control columns.
ALTER TABLE s_msroute DROP COLUMN IF EXISTS s_access_role_ids;
ALTER TABLE s_api_endpoint DROP COLUMN IF EXISTS s_access_role_ids;

-- Remove obsolete app link column if still present on navset_role.
ALTER TABLE s_navset_role DROP COLUMN IF EXISTS s_app_id;

-- Remove deprecated access scope/role_id columns if they remain on s_role.
ALTER TABLE s_role DROP COLUMN IF EXISTS s_access_scope;
ALTER TABLE s_role DROP COLUMN IF EXISTS s_access_role_ids;

-- Ensure role table has no stray s_ms_id from legacy app scoping.
ALTER TABLE s_role DROP COLUMN IF EXISTS s_ms_id;
