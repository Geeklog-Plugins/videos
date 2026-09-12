from pathlib import Path


def replace_once(path, old, new):
    text = path.read_text(encoding='utf-8')
    if old in text:
        text = text.replace(old, new, 1)
        path.write_text(text, encoding='utf-8')
        return
    if new in text:
        return
    raise SystemExit('Unable to normalize ' + str(path))


pool = Path('classes/Videos_PermanentPool.php')
replace_once(
    pool,
    """        $isEditorialSave = ($state === 'added' && !$wasPublished)
            || ($state === 'pinned' && !$wasPinned)
            || ($state === 'unpinned' && $wasPinned);
        if ($isEditorialSave) {
            if (function_exists('VIDEOS_signalSaved')) {
                VIDEOS_signalSaved($videoId);
                VIDEOS_signalSaved('catalogue');
                VIDEOS_signalSaved('channels');
            } elseif (function_exists('PLG_itemSaved')) {
                PLG_itemSaved($videoId, 'videos');
            }
        }
        return true;
""",
    """        $isEditorialSave = in_array(
            $state,
            array('added', 'pinned', 'unpinned'),
            true
        );
        $isEditorialDelete = $wasPublished &&
            in_array($state, array('removed', 'excluded'), true);

        if ($isEditorialDelete) {
            $this->signalDeleted($videoId);
            $this->signalCollections();
        } elseif ($isEditorialSave) {
            $this->signalSaved($videoId);
            $this->signalCollections();
        }
        return true;
"""
)
replace_once(
    pool,
    """        foreach ($items as $videoId => $item) {
            if (!isset($previousItems[$videoId])) {
                if (function_exists('VIDEOS_signalSaved')) {
                    VIDEOS_signalSaved($videoId);
                    VIDEOS_signalSaved('catalogue');
                } elseif (function_exists('PLG_itemSaved')) {
                    PLG_itemSaved($videoId, 'videos');
                }
            }
        }
        return $document['data'];
""",
    """        $changed = false;
        foreach ($items as $videoId => $item) {
            if (!isset($previousItems[$videoId])) {
                $this->signalSaved($videoId);
                $changed = true;
            }
        }
        foreach ($previousItems as $videoId => $item) {
            if (!isset($items[$videoId])) {
                $this->signalDeleted($videoId);
                $changed = true;
            }
        }
        if ($changed) {
            $this->signalCollections();
        }
        return $document['data'];
"""
)
replace_once(
    pool,
    """    private function listSet($value)
""",
    """    private function signalSaved($id)
    {
        if (function_exists('VIDEOS_signalSaved')) {
            VIDEOS_signalSaved($id);
        } elseif (function_exists('PLG_itemSaved')) {
            PLG_itemSaved($id, 'videos');
        }
    }

    private function signalDeleted($id)
    {
        if (function_exists('VIDEOS_signalDeleted')) {
            VIDEOS_signalDeleted($id);
        } elseif (function_exists('PLG_itemDeleted')) {
            PLG_itemDeleted($id, 'videos');
        }
    }

    private function signalCollections()
    {
        $this->signalSaved('catalogue');
        $this->signalSaved('channels');
        $this->signalSaved('rankings:videos');
        $this->signalSaved('rankings:channels');
    }

    private function listSet($value)
"""
)

moderation = Path('classes/Videos_Moderation.php')
replace_once(
    moderation,
    """        $saved = $this->setState(
            'video',
            $videoId,
            $state,
            $reason,
            $actorHash
        );
        if ($saved) {
            $this->signalVideoDecision($videoId);
        }
""",
    """        $before = $this->getVideoState($videoId);
        $saved = $this->setState(
            'video',
            $videoId,
            $state,
            $reason,
            $actorHash
        );
        if ($saved) {
            $beforeState = isset($before['state']) ? $before['state'] : 'neutral';
            $this->signalVideoDecision($videoId, $beforeState, $state);
        }
"""
)
replace_once(
    moderation,
    """        $saved = $this->setState(
            'channel',
            $channelId,
            $state,
            $reason,
            $actorHash
        );
        if ($saved) {
            $this->signalChannelDecision($channelId);
        }
""",
    """        $before = $this->getChannelState($channelId);
        $saved = $this->setState(
            'channel',
            $channelId,
            $state,
            $reason,
            $actorHash
        );
        if ($saved) {
            $beforeState = isset($before['state']) ? $before['state'] : 'neutral';
            $this->signalChannelDecision($channelId, $beforeState, $state);
        }
"""
)
start = moderation.read_text(encoding='utf-8')
old_start = start.index('    private function signalVideoDecision(')
old_end = start.index('    private function setState(', old_start)
new_block = """    private function signalVideoDecision($videoId, $beforeState, $afterState)
    {
        if ($beforeState === $afterState) {
            return;
        }

        $pool = new Videos_PermanentPool(
            $this->store,
            new Videos_Cache($this->store)
        );
        if ($pool->contains($videoId)) {
            if ($afterState === 'blocked') {
                $this->signalDeleted($videoId);
            } elseif ($beforeState === 'blocked') {
                $this->signalSaved($videoId);
            }
        }

        $this->signalSaved('catalogue');
        $this->signalSaved('rankings:videos');
    }

    private function signalChannelDecision($channelId, $beforeState, $afterState)
    {
        if ($beforeState === $afterState) {
            return;
        }

        $beforeExcluded = in_array(
            $beforeState,
            array('blocked', 'disabled'),
            true
        );
        $afterExcluded = in_array(
            $afterState,
            array('blocked', 'disabled'),
            true
        );

        if ($afterExcluded && !$beforeExcluded) {
            $this->signalDeleted('channel:' . $channelId);
        } else {
            $this->signalSaved('channel:' . $channelId);
        }

        if ($beforeExcluded !== $afterExcluded) {
            $this->signalChannelVideos($channelId, $afterExcluded);
        }

        $this->signalSaved('catalogue');
        $this->signalSaved('rankings:channels');
        $this->signalSaved('channels');
        $this->signalSaved('rankings:videos');
    }

    private function signalChannelVideos($channelId, $deleted)
    {
        $pool = new Videos_PermanentPool(
            $this->store,
            new Videos_Cache($this->store)
        );
        $records = $pool->records();
        $items = isset($records['items']) && is_array($records['items'])
            ? $records['items'] : array();
        $cache = new Videos_Cache($this->store);

        foreach ($items as $videoId => $item) {
            $video = $cache->getVideo($videoId, true);
            if (!is_array($video) || empty($video['snippet']['channelId']) ||
                (string) $video['snippet']['channelId'] !== $channelId) {
                continue;
            }
            if ($deleted) {
                $this->signalDeleted($videoId);
            } else {
                $this->signalSaved($videoId);
            }
        }
    }

    private function signalSaved($id)
    {
        if (function_exists('VIDEOS_signalSaved')) {
            VIDEOS_signalSaved($id);
        } elseif (function_exists('PLG_itemSaved')) {
            PLG_itemSaved($id, 'videos');
        }
    }

    private function signalDeleted($id)
    {
        if (function_exists('VIDEOS_signalDeleted')) {
            VIDEOS_signalDeleted($id);
        } elseif (function_exists('PLG_itemDeleted')) {
            PLG_itemDeleted($id, 'videos');
        }
    }

"""
start = start[:old_start] + new_block + start[old_end:]
moderation.write_text(start, encoding='utf-8')
