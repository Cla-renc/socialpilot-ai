import 'package:flutter/material.dart';

import '../../services/analytics_service.dart';

class AiRecommendationsScreen extends StatefulWidget {
  const AiRecommendationsScreen({super.key});

  @override
  State<AiRecommendationsScreen> createState() =>
      _AiRecommendationsScreenState();
}

class _AiRecommendationsScreenState extends State<AiRecommendationsScreen> {
  final AnalyticsService _service = AnalyticsService();
  List<dynamic> _recs = [];
  bool _loading = true;
  String? _error;
  String _status = 'pending';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _service.getRecommendations(status: _status);
      final items = data['data'] ?? data['items'] ?? [];
      setState(() {
        _recs = items is List ? items : [];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _accept(int id) async {
    try {
      await _service.acceptRecommendation(id);
      _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }

  Future<void> _dismiss(int id) async {
    try {
      await _service.dismissRecommendation(id);
      _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }

  Color _confidenceColor(String? confidence) {
    switch (confidence?.toLowerCase()) {
      case 'high':
        return Colors.green;
      case 'medium':
        return Colors.orange;
      case 'low':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('AI Recommendations'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: Column(
        children: [
          // Status filter chips
          Padding(
            padding: const EdgeInsets.all(12),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: ['pending', 'accepted', 'dismissed', 'all']
                    .map((s) => Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: FilterChip(
                            label: Text(
                                s[0].toUpperCase() + s.substring(1)),
                            selected: _status == s,
                            onSelected: (_) {
                              setState(() => _status = s);
                              _load();
                            },
                          ),
                        ))
                    .toList(),
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_error!,
                                style: const TextStyle(color: Colors.red)),
                            const SizedBox(height: 16),
                            FilledButton(
                                onPressed: _load,
                                child: const Text('Retry')),
                          ],
                        ),
                      )
                    : _recs.isEmpty
                        ? const Center(
                            child: Padding(
                              padding: EdgeInsets.all(24),
                              child: Text(
                                'No recommendations available.\nCollect analytics first to generate AI insights.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: Colors.grey),
                              ),
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.builder(
                              padding: const EdgeInsets.all(12),
                              itemCount: _recs.length,
                              itemBuilder: (_, i) {
                                final rec = _recs[i] as Map<String, dynamic>;
                                final id = rec['id'] as int? ?? 0;
                                final confidence =
                                    rec['confidence']?.toString();
                                final status =
                                    rec['status']?.toString() ?? 'pending';
                                return Card(
                                  margin: const EdgeInsets.only(bottom: 10),
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                rec['title']?.toString() ??
                                                    rec['recommendation_type']
                                                        ?.toString() ??
                                                    'Recommendation',
                                                style: const TextStyle(
                                                    fontWeight:
                                                        FontWeight.bold),
                                              ),
                                            ),
                                            if (confidence != null)
                                              Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                        horizontal: 8,
                                                        vertical: 3),
                                                decoration: BoxDecoration(
                                                  color: _confidenceColor(
                                                      confidence),
                                                  borderRadius:
                                                      BorderRadius.circular(12),
                                                ),
                                                child: Text(
                                                  confidence[0].toUpperCase() +
                                                      confidence.substring(1),
                                                  style: const TextStyle(
                                                      color: Colors.white,
                                                      fontSize: 12),
                                                ),
                                              ),
                                          ],
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          rec['description']?.toString() ?? '',
                                          style: const TextStyle(
                                              color: Colors.grey, fontSize: 13),
                                        ),
                                        if (rec['priority'] != null) ...[
                                          const SizedBox(height: 4),
                                          Text(
                                            'Priority: ${rec['priority']}',
                                            style: const TextStyle(
                                                fontSize: 12,
                                                color: Colors.grey),
                                          ),
                                        ],
                                        if (status == 'pending') ...[
                                          const SizedBox(height: 10),
                                          Row(
                                            children: [
                                              OutlinedButton(
                                                onPressed: () => _accept(id),
                                                child: const Text('Accept'),
                                              ),
                                              const SizedBox(width: 8),
                                              TextButton(
                                                onPressed: () => _dismiss(id),
                                                child: const Text('Dismiss'),
                                              ),
                                            ],
                                          ),
                                        ] else
                                          Padding(
                                            padding:
                                                const EdgeInsets.only(top: 6),
                                            child: Text(
                                              status[0].toUpperCase() +
                                                  status.substring(1),
                                              style: TextStyle(
                                                fontSize: 12,
                                                color: status == 'accepted'
                                                    ? Colors.green
                                                    : Colors.grey,
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
