#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ADMIN_FILES = [ROOT / 'admin' / 'actions.php', ROOT / 'admin' / 'stats.php']
LANG_FILES = {
    ROOT / 'language' / 'english.php': 0,
    ROOT / 'language' / 'french_france.php': 1,
}
BEGIN = '// VIDEOS ADMIN COMPAT KEYS 0.19.0'
END = '// END VIDEOS ADMIN COMPAT KEYS 0.19.0'

# Temporary compatibility aliases for keys produced by the failed hash migration.
# They live in the language files, never in the page templates.
ALIASES = {
    # Actions: messages and UI
    'text_ad4bee7e9ff4': ('Videos plugin storage is unavailable.', 'Le stockage du plugin Videos est indisponible.'),
    'text_e3a2abb1df9e': ('The security token has expired. Please try again.', 'Le jeton de sécurité a expiré. Veuillez recommencer.'),
    'text_01bc6389bfb4': ('The YouTube Data API key has been saved.', 'La clé YouTube Data API a été enregistrée.'),
    'text_5f254586a799': ('The API key is invalid.', 'La clé API est invalide.'),
    'text_7f47edc0ce97': ('The test query is invalid.', 'La requête de test est invalide.'),
    'text_7b0911c5e838': ('The test search failed.', 'La recherche de test a échoué.'),
    'text_f34564089709': (' valid video(s) found.', ' vidéo(s) valide(s) trouvée(s).'),
    'text_b9af87bba9de': ('The seed query is invalid.', 'La requête d’amorçage est invalide.'),
    'text_2486b5cd3e93': (' video(s) added to the reservoir.', ' vidéo(s) ajoutée(s) au réservoir.'),
    'text_bed0f10568ed': ('Reservoir seeding failed.', 'L’amorçage du réservoir a échoué.'),
    'text_3e3769ea8e57': (' cache entrie(s) deleted.', ' entrée(s) de cache supprimée(s).'),
    'text_0ff52fd5762a': ('Partial cleanup: ', 'Nettoyage partiel : '),
    'text_6c77e30d5f18': (' deleted, ', ' supprimée(s), '),
    'text_935b440d1a9c': (' failure(s).', ' échec(s).'),
    'text_3b343dfb9214': ('Ranking rebuild failed.', 'La reconstruction des classements a échoué.'),
    'text_c8e38f9b9716': ('Rankings rebuilt: ', 'Classements reconstruits : '),
    'text_e0a70ebd0d32': (' ranked video(s).', ' vidéo(s) classée(s).'),
    'text_cc6acc866d8d': ('Permanent catalogue rebuild failed.', 'La reconstruction du catalogue permanent a échoué.'),
    'text_fb0584cd8c48': ('The permanent catalogue has been rebuilt.', 'Le catalogue permanent a été reconstruit.'),
    'text_82147ba36284': ('Invalid YouTube ID or URL.', 'ID ou URL YouTube invalide.'),
    'text_77f7f85bea7d': ('The video is unavailable, private, not embeddable, or rejected by plugin policy.', 'La vidéo est introuvable, privée, non intégrable ou refusée par la politique du plugin.'),
    'text_6b92eea0db9c': ('This video is currently blocked by moderation.', 'Cette vidéo est actuellement bloquée par la modération.'),
    'text_1b98ef900ab7': ('Video added to the permanent catalogue.', 'Vidéo ajoutée au catalogue permanent.'),
    'text_d51726c9d960': ('Unable to add the video to the permanent catalogue.', 'Impossible d’ajouter la vidéo au catalogue permanent.'),
    'text_094f5ad0c0fe': ('Editorial decision saved.', 'Décision éditoriale enregistrée.'),
    'text_fa630f6fba54': ('Unable to save this decision.', 'Impossible d’enregistrer cette décision.'),
    'text_974483b3c0bd': ('No public Videos URL to submit.', 'Aucune URL publique Videos à signaler.'),
    'text_f9f0422359a1': ('The IndexNow batch could not be sent.', 'Le batch IndexNow n’a pas pu être envoyé.'),
    'text_66d82f59b38e': (' Videos URL(s) sent to IndexNow in one batch.', ' URL(s) Videos envoyée(s) à IndexNow en un seul batch.'),
    'text_bf4753222ec0': ('The IndexNow plugin is unavailable. No fake content creation event was emitted.', 'Le plugin IndexNow n’est pas disponible. Aucune fausse création de contenu n’a été émise.'),
    'text_e4a4d74ceeba': ('Add a video directly by its YouTube ID or URL. It is fetched, cached, added to the permanent catalogue, then signalled through Geeklog events.', 'Ajoutez directement une vidéo par son ID ou son URL YouTube. Elle est récupérée, mise en cache, ajoutée au catalogue permanent puis signalée via les événements Geeklog.'),
    'text_2dcd74f139c2': (' or https://youtu.be/…', ' ou https://youtu.be/…'),
    'text_20c9e4289922': ('Add to permanent catalogue', 'Ajouter au catalogue permanent'),
    'text_3696c9b66256': ('Permanent catalogue', 'Catalogue permanent'),
    'text_d0d466d799cb': ('No retained video.', 'Aucune vidéo conservée.'),
    'text_12bd8de0f11f': ('Pinned', 'Épinglée'),
    'text_4bc9bbe28d82': ('Permanent', 'Permanente'),
    'text_6c1f6aad64af': ('Unpin', 'Désépingler'),
    'text_9f4045dd74f4': ('Pin', 'Épingler'),
    'text_ddcf6449d058': ('Remove from selection', 'Retirer de la sélection'),
    'text_6ce15f0ae2c2': ('Exclude from future selections', 'Exclure des sélections futures'),
    'text_5076293e41de': ('Videos excluded from the pool', 'Vidéos exclues du fonds'),
    'text_a38b9729e18c': ('Allow again', 'Réautoriser'),
    'text_914c6f2a00bd': ('Rebuild permanent catalogue', 'Reconstruire le catalogue permanent'),
    'text_cfb801cd863c': ('API key configured.', 'Clé API configurée.'),
    'text_a41badeb3bf5': ('API key missing.', 'Clé API absente.'),
    'text_4ca61851cb1a': ('A YouTube Data API key is required to search for new videos and retrieve data for an uncached video. Cached videos remain available without a new API call.', 'Une clé YouTube Data API est nécessaire pour rechercher de nouvelles vidéos et récupérer les données d’une vidéo qui n’est pas encore en cache. Les vidéos déjà mises en cache restent consultables sans nouvel appel API.'),
    'text_7a468e16ab17': ('Replace API key', 'Remplacer la clé API'),
    'text_a05995d3d967': ('Add API key', 'Ajouter une clé API'),
    'text_a6c9feab7f6b': ('Replace key', 'Remplacer la clé'),
    'text_e4d5d3b1e0c6': ('Save key', 'Enregistrer la clé'),
    'text_559548968cfd': ('Test search', 'Tester la recherche'),
    'text_5d8652cbb6d4': ('Seed query', 'Requête d’amorçage'),
    'text_3b5c2464103e': ('Seed reservoir', 'Amorcer le réservoir'),
    'text_50cc32da6a35': ('Rebuild rankings', 'Reconstruire les classements'),
    'text_c756339c6ce3': ('Repair tools', 'Outils de réparation'),
    'text_383ccd02e996': ('Index existing pages', 'Indexation des pages existantes'),
    'text_7f66e4e9d432': ('The catch-up process inventories public pages and sends them to IndexNow in batch mode.', 'Le rattrapage inventorie les pages publiques et les envoie à IndexNow en mode batch.'),
    'text_1167f948e9e0': ('Send existing pages to IndexNow', 'Envoyer les pages existantes à IndexNow'),
    'text_2663d0921433': ('YouTube quota is suspended (', 'Le quota YouTube est suspendu ('),
    'text_4c9ee5b96b2f': ('The local YouTube search limit has been reached (', 'La limite locale de recherches YouTube est atteinte ('),
    'text_e112686715a8': (' today).', ' aujourd’hui).'),
    'text_3d0c6584d55c': ('Last YouTube error: ', 'Dernière erreur YouTube : '),
    'text_ed0d19fdd3ad': ('See Statistics > YouTube API activity for diagnostics.', 'Consultez Statistiques > Activité YouTube API pour le diagnostic.'),

    # Statistics
    'text_50dbebed149b': ('Public and editorial content', 'Contenu public et éditorial'),
    'text_5793dbff4b44': ('Videos in reservoir', 'Vidéos dans le réservoir'),
    'text_be9104ffe37e': ('Local discovery corpus', 'Corpus de découverte local'),
    'text_ad66e72e75ec': ('Searchable videos', 'Vidéos recherchables'),
    'text_36868dbab548': ('Public corpus used by Geeklog and the catalogue', 'Corpus public utilisé par Geeklog et le catalogue'),
    'text_b3d4e15e39bf': ('Global ranking', 'Classement global'),
    'text_4bf56ef2cc6c': ('Videos with local signals', 'Vidéos ayant des signaux locaux'),
    'text_b9bef52d4aa3': ('Ranked channels', 'Chaînes classées'),
    'text_d50b0123f1de': ('Channels from the local ranking', 'Chaînes issues du classement local'),
    'text_16bb358770de': ('Priority channels', 'Chaînes prioritaires'),
    'text_b83fc7e45471': ('Active editorial decisions', 'Décisions éditoriales actives'),
    'text_184e3abfc03a': ('Videos retained permanently', 'Vidéos conservées durablement'),
    'text_4072238c8cc7': ('Pinned videos', 'Vidéos épinglées'),
    'text_7d57e2bb142d': ('Strong selections, including legacy 0.17 pins', 'Sélections fortes, y compris les anciens épinglages 0.17'),
    'text_f10feb2274ea': ('Excluded from pool', 'Exclues du fonds'),
    'text_007f0fff6df3': ('Explicit editorial exclusions', 'Exclusions éditoriales explicites'),
    'text_65748d98f850': ('YouTube API activity', 'Activité YouTube API'),
    'text_696467c95b05': ('Searches today', 'Recherches aujourd’hui'),
    'text_7cd3fe26311e': ('search.list calls', 'Appels search.list'),
    'text_5e2d327ccd27': ('Video calls', 'Appels vidéos'),
    'text_01c816c87508': ('videos.list details', 'Détails videos.list'),
    'text_801bc4757eb1': ('Channel calls', 'Appels chaînes'),
    'text_c4620a96701e': ('channels.list details', 'Détails channels.list'),
    'text_229f2b1943a8': ('Quota suspended', 'Quota suspendu'),
    'text_a715911d13d4': ('Suspension after a quota error reported by YouTube', 'Suspension après une erreur de quota signalée par YouTube'),
    'text_27ff1cce04c6': ('Local search limit', 'Limite locale recherches'),
    'text_fee22e6ee72b': ('Daily limit configured in Videos', 'Plafond quotidien configuré dans Videos'),
    'text_2dc02504e6fd': ('Last search', 'Dernière recherche'),
    'text_7a78c348afb3': ('Last authorized search.list reservation', 'Dernière réservation search.list autorisée'),
    'text_16e8b06800bc': ('Last API error', 'Dernière erreur API'),
    'text_160478e94434': ('Last success', 'Dernier succès'),
    'text_4bdda76e1cba': ('Last valid API response', 'Dernière réponse API valide'),
    'text_41059bb643de': ('Last rejected call:', 'Dernier appel refusé :'),
    'text_55532ba13b84': ('Never', 'Jamais'),
    'text_5335de22db2d': ('Search results', 'Résultats de recherche'),
    'text_6d32810e19b0': ('Video information', 'Informations des vidéos'),
    'text_13849355df98': ('Channel information', 'Informations des chaînes'),
    'text_412bbe5e0703': ('Availability checks', 'Vérifications de disponibilité'),
    'text_fcbf6472e074': ('Latest entry', 'Entrée la plus récente'),
    'text_21f56c736dd7': ('Geeklog integration', 'Intégration Geeklog'),
    'text_1fac67f04852': ('Geeklog search', 'Recherche Geeklog'),
    'text_f31987d233f9': ('Geeklog statistics', 'Statistiques Geeklog'),
    'text_1ee065b38a78': ('Catalogue search', 'Recherche du catalogue'),
    'text_1c2d095511c3': ('ItemInfo interoperability', 'Interopérabilité ItemInfo'),
    'text_b9cb1c7d82fc': ('Available', 'Disponible'),
    'text_7cc7897d5382': ('Unavailable', 'Indisponible'),
    'text_804661ad2853': ('Developer information', 'Informations développeur'),
    'text_994b6ba1c20e': ('Needs review', 'À vérifier'),
    'text_605c3eed118a': ('Technical diagnostics', 'Diagnostic technique'),
    'text_5e1f9ce9c113': ('No video is available for the SEO diagnostics.', 'Aucune vidéo disponible pour le diagnostic SEO.'),
}


def strip_block(text):
    if BEGIN in text and END in text:
        text = re.sub(re.escape(BEGIN) + r'.*?' + re.escape(END) + r'\s*', '', text, flags=re.S)
    return text


def ensure_aliases(path, language_index):
    text = strip_block(path.read_text(encoding='utf-8'))
    lines = [BEGIN, '$LANG_VIDEOS_ADMIN = array_merge($LANG_VIDEOS_ADMIN, array(']
    for key in sorted(ALIASES):
        value = ALIASES[key][language_index].replace('\\', '\\\\').replace("'", "\\'")
        lines.append("    '%s' => '%s'," % (key, value))
    lines.extend(['));', END, ''])
    marker = '$LANG_VIDEOS_FAQ = array('
    pos = text.find(marker)
    if pos < 0:
        raise RuntimeError('Unable to locate FAQ language block in %s' % path)
    text = text[:pos] + '\n'.join(lines) + '\n' + text[pos:]
    path.write_text(text, encoding='utf-8')


def defined_keys(text):
    return set(re.findall(r"'((?:text_)[0-9a-f]{12})'\s*=>", text))


def used_keys():
    keys = set()
    for path in ADMIN_FILES:
        text = path.read_text(encoding='utf-8')
        keys.update(re.findall(r"VIDEOS_adminText\('((?:text_)[0-9a-f]{12})'\)", text))
    return keys


def validate():
    used = used_keys()
    for path in LANG_FILES:
        defined = defined_keys(path.read_text(encoding='utf-8'))
        missing = sorted(used - defined)
        if missing:
            raise RuntimeError('%s missing admin keys: %s' % (path.name, ', '.join(missing)))
    print('Admin language keys complete for Actions and Statistics: %d keys.' % len(used))


def main():
    for path, language_index in LANG_FILES.items():
        ensure_aliases(path, language_index)
    validate()


if __name__ == '__main__':
    main()
