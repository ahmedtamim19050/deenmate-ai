import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/common.dart';
import '../../shared/providers/providers.dart';
import '../../shared/repositories/cached_fetch.dart';

final hadithCollectionsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final data = await cachedFetch(ref.read(databaseProvider), 'hadith:collections', () => ref.read(apiClientProvider).get('/hadith/collections'));
  return (data['data'] as List).cast<Map<String, dynamic>>();
});

class HadithCollectionsScreen extends ConsumerWidget {
  const HadithCollectionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(l.hadithTitle)),
      body: AsyncView(
        value: ref.watch(hadithCollectionsProvider),
        onRetry: () => ref.invalidate(hadithCollectionsProvider),
        builder: (rows) => rows.isEmpty
            ? StateMessage(icon: Icons.library_books_outlined, title: l.hadithUnavailableTitle, body: l.hadithUnavailableBody)
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  for (final c in rows)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: NavTile(
                        icon: Icons.library_books_outlined,
                        title: c['name'] as String,
                        subtitle: '${c['compiler'] ?? ''} · ${l.recordsCount(c['records_count'] as int)}',
                        onTap: () => context.push('/hadith/${c['id']}'),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}

class HadithRecordsScreen extends ConsumerStatefulWidget {
  const HadithRecordsScreen({super.key, required this.collectionId});

  final int collectionId;

  @override
  ConsumerState<HadithRecordsScreen> createState() => _HadithRecordsScreenState();
}

class _HadithRecordsScreenState extends ConsumerState<HadithRecordsScreen> {
  final List<Map<String, dynamic>> _rows = [];
  Map<String, dynamic>? _collection;
  int _page = 0;
  int _lastPage = 1;
  bool _loading = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (_loading || _page >= _lastPage) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = _page + 1;
      final data = await cachedFetch(
        ref.read(databaseProvider),
        'hadith:${widget.collectionId}:$page',
        () => ref.read(apiClientProvider).get('/hadith/collections/${widget.collectionId}/records', query: {'page': page}),
      );
      if (!mounted) return;
      setState(() {
        _rows.addAll((data['data'] as List).cast<Map<String, dynamic>>());
        _collection = data['collection'] as Map<String, dynamic>?;
        _page = page;
        _lastPage = (data['meta']['last_page'] as num).toInt();
      });
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      appBar: AppBar(title: Text(_collection?['name'] as String? ?? l.hadithTitle)),
      body: _error != null && _rows.isEmpty
          ? ErrorState(error: _error!, onRetry: _load)
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _rows.length + 1,
              itemBuilder: (_, i) {
                if (i == _rows.length) {
                  if (_loading) return const Padding(padding: EdgeInsets.all(20), child: Center(child: CircularProgressIndicator()));
                  if (_page < _lastPage) return OutlinedButton(onPressed: _load, child: Text(l.loadMore));
                  return Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(l.sourceLabel('${_collection?['source_url'] ?? l.unknown}'), style: context.text.bodySmall),
                  );
                }
                final h = _rows[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(l.hadithNumber('${h['number']}'), style: context.text.titleSmall),
                        if (h['book'] != null) Text('${h['book']}', style: context.text.bodySmall),
                        if (h['arabic'] != null) ...[
                          const SizedBox(height: 10),
                          Text(h['arabic'] as String, textDirection: TextDirection.rtl, textAlign: TextAlign.right, style: const TextStyle(fontFamily: 'NotoNaskhArabic', fontSize: 19, height: 1.8)),
                        ],
                        const SizedBox(height: 10),
                        SelectableText(h['translation'] as String? ?? l.translationUnavailable),
                        const SizedBox(height: 8),
                        Text(h['grade'] == null ? l.gradeNotDocumented : l.gradeLabel('${h['grade']}', '${h['grade_source'] ?? l.unknown}'), style: context.text.bodySmall),
                        if (h['source_reference'] != null) Text('${h['source_reference']}', style: context.text.bodySmall),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}
