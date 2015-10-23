BEGIN;

--
-- We drop and recreate the stats table which was not used yet.
--
DROP TABLE DeviceStats;
CREATE TABLE DeviceStats (
   DeviceId INTEGER UNSIGNED REFERENCES Device DEFAULT 0,
   SampleTime DATETIME NOT NULL,
   ReadTime BIGINT UNSIGNED DEFAULT 0,
   WriteTime BIGINT UNSIGNED DEFAULT 0,
   ReadBytes BIGINT UNSIGNED DEFAULT 0,
   WriteBytes BIGINT UNSIGNED DEFAULT 0,
   SpoolSize BIGINT UNSIGNED DEFAULT 0,
   NumWaiting INTEGER DEFAULT 0,
   NumWriters INTEGER DEFAULT 0,
   MediaId INTEGER UNSIGNED REFERENCES Media DEFAULT 0,
   VolCatBytes BIGINT UNSIGNED DEFAULT 0,
   VolCatFiles BIGINT UNSIGNED DEFAULT 0,
   VolCatBlocks BIGINT UNSIGNED DEFAULT 0
);

--
-- We drop and recreate the stats table which was not used yet.
--
DROP TABLE JobStats;
CREATE TABLE JobStats (
   DeviceId INTEGER UNSIGNED REFERENCES Device DEFAULT 0,
   SampleTime DATETIME NOT NULL,
   JobId INTEGER UNSIGNED REFERENCES Job NOT NULL,
   JobFiles INTEGER UNSIGNED DEFAULT 0,
   JobBytes BIGINT UNSIGNED DEFAULT 0
);

CREATE TABLE TapeAlerts (
   DeviceId INTEGER UNSIGNED REFERENCES Device DEFAULT 0,
   SampleTime DATETIME NOT NULL,
   AlertFlags BIGINT UNSIGNED DEFAULT 0
);

DROP TABLE CDImages;

UPDATE Version SET VersionId = 2003;
COMMIT;