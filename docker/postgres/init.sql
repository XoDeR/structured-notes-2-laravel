SELECT 'CREATE DATABASE structured_notes_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'structured_notes_testing')\gexec
