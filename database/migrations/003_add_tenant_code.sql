CREATE SEQUENCE tenants_code_seq;

ALTER TABLE tenants
ADD COLUMN code VARCHAR(20);

UPDATE tenants
SET code = 'TEN' || LPAD(nextval('tenants_code_seq')::TEXT, 6, '0')
WHERE code IS NULL;

ALTER TABLE tenants
ALTER COLUMN code SET NOT NULL;

ALTER TABLE tenants
ADD CONSTRAINT tenants_code_unique UNIQUE (code);

ALTER TABLE tenants
ALTER COLUMN code SET DEFAULT ('TEN' || LPAD(nextval('tenants_code_seq')::TEXT, 6, '0'));

CREATE FUNCTION prevent_tenants_code_update()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.code IS DISTINCT FROM OLD.code THEN
        RAISE EXCEPTION 'tenants.code is immutable';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tenants_code_immutable
BEFORE UPDATE OF code ON tenants
FOR EACH ROW
EXECUTE FUNCTION prevent_tenants_code_update();
