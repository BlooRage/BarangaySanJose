ALTER TABLE casereportstbl
    ADD COLUMN IF NOT EXISTS incident_area_number VARCHAR(20) NULL AFTER incident_place;
