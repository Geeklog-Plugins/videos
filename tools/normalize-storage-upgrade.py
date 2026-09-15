#!/usr/bin/env python3
from pathlib import Path

path = Path('install_updates.php')
text = path.read_text(encoding='utf-8')

old_019 = """function videos_update_0_18_0_to_0_19_0()
{
    // 0.19.0 changes PHP/CSS integration only; no SQL/config migration.
    return true;
}
"""

new_019 = """function videos_update_0_18_0_to_0_19_0()
{
    global $_CONF;

    // Persistent storage migration is an explicit site-scoped upgrade step.
    // Normal runtime bootstrap only keeps legacy storage as a compatibility
    // fallback until this site's upgrade has completed successfully.
    $bootstrap = new Videos_Bootstrap($_CONF);
    if (!$bootstrap->isReady()) {
        COM_errorLog(
            'Videos upgrade 0.19.0: storage bootstrap is not ready.',
            1
        );
        return false;
    }

    if (!$bootstrap->migrateLegacyStorage()) {
        $status = $bootstrap->getMigrationStatus();
        COM_errorLog(
            'Videos upgrade 0.19.0: persistent storage migration failed '
            . '(source=' . $bootstrap->getLegacyDataRoot()
            . ', destination=' . $bootstrap->getPreferredDataRoot()
            . ', copied=' . (isset($status['copied']) ? (int) $status['copied'] : 0)
            . ', skipped=' . (isset($status['skipped']) ? (int) $status['skipped'] : 0)
            . ', failed=' . (isset($status['failed']) ? (int) $status['failed'] : 0)
            . '). Legacy data was preserved.',
            1
        );
        return false;
    }

    return true;
}
"""

old_017 = """function videos_update_0_17_0_to_0_17_1()
{
    // Loading the plugin already performs the idempotent storage migration.
    return true;
}
"""

new_017 = """function videos_update_0_17_0_to_0_17_1()
{
    // Storage remains readable in its legacy location. The explicit migration
    // is performed by the controlled 0.18.0 -> 0.19.0 upgrade step.
    return true;
}
"""

if old_019 in text:
    text = text.replace(old_019, new_019, 1)
elif new_019 not in text:
    raise SystemExit('Cannot locate the 0.18.0 -> 0.19.0 upgrade callback')

if old_017 in text:
    text = text.replace(old_017, new_017, 1)
elif new_017 not in text:
    raise SystemExit('Cannot locate the 0.17.0 -> 0.17.1 upgrade callback')

path.write_text(text, encoding='utf-8')
