import 'package:flutter/material.dart';

import '../../services/analytics_service.dart';

class DataCollectionStatusScreen extends StatefulWidget {
  const DataCollectionStatusScreen({super.key});

  @override
  State<DataCollectionStatusScreen> createState() =>
      _DataCollectionStatusScreenState();
}

class _DataCollectionStatusScreenState
    extends State<DataCollectionStatusScreen> {
  final AnalyticsService _service = AnalyticsService();
  List<dynamic> _logs = [];
  bool _loading = true;
  String? _error;

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
      final data = await _service.getCollectionStatus();
      final items = data['data'] ?? [];
      setState(() {
        _logs = items is List ? items : [];
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Color _statusColor(String? status) {
    switch (status) {
      case 'completed':
        return Colors.green;
      case 'failed':
        return Colors.red;
      case 'running':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  IconData _statusIcon(String? status) {
    switch (status) {
      case 'completed':
        return Icons.check_circle_outline;
      case 'failed':
        return Icons.error_outline;
      case 'running':
        return Icons.sync;
      default:
        return Icons.help_outline;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Collection Status'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 16),
                      FilledButton(
                          onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : _logs.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                          'No collection logs found.\nConnect social accounts and trigger analytics collection.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.grey),
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _logs.length,
                        itemBuilder: (_, i) {
                          final log = _logs[i] as Map<String, dynamic>;
                          final status = log['status']?.toString();
                          final records =
                              log['records_collected']?.toString() ?? '0';
                          final platform =
                              log['platform']?.toString() ?? 'unknown';
                          final type =
                              log['collection_type']?.toString() ?? '';
                          final startedAt =
                              log['started_at']?.toString() ?? '';
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: ListTile(
                              leading: Icon(
                                _statusIcon(status),
                                color: _statusColor(status),
                              ),
                              title: Text(
                                '${platform[0].toUpperCase()}${platform.substring(1)} · $type',
                              ),
                              subtitle: Text(
                                '$records records · $startedAt',
                                style: const TextStyle(fontSize: 12),
                              ),
                              trailing: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 7, vertical: 3),
                                decoration: BoxDecoration(
                                  color: _statusColor(status),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  status ?? '?',
                                  style: const TextStyle(
                                      color: Colors.white, fontSize: 11),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
