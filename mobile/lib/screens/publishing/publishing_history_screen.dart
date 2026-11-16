import 'package:flutter/material.dart';

import '../../models/publishing_attempt.dart';
import '../../services/publishing_service.dart';
import 'publishing_details_screen.dart';

class PublishingHistoryScreen extends StatefulWidget {
  const PublishingHistoryScreen({super.key, this.service});

  final PublishingService? service;

  @override
  State<PublishingHistoryScreen> createState() =>
      _PublishingHistoryScreenState();
}

class _PublishingHistoryScreenState extends State<PublishingHistoryScreen> {
  late final PublishingService _service;
  List<PublishingAttempt> _attempts = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? PublishingService();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final response = await _service.getAttempts();
      final rows = response['data'];
      if (rows is List) {
        _attempts = rows
            .map((e) =>
                PublishingAttempt.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
      } else {
        _attempts = [];
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Publishing History')),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _attempts.isEmpty
                      ? const Center(
                          child: Text('No publishing attempts found.'),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _attempts.length,
                          itemBuilder: (context, index) {
                            final attempt = _attempts[index];
                            return _AttemptCard(
                              attempt: attempt,
                              onTap: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => PublishingDetailsScreen(
                                      attempt: attempt,
                                    ),
                                  ),
                                );
                              },
                            );
                          },
                        ),
                ),
    );
  }
}

class _AttemptCard extends StatelessWidget {
  const _AttemptCard({required this.attempt, required this.onTap});

  final PublishingAttempt attempt;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      attempt.platform.toUpperCase(),
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    if (attempt.publishedAt != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        'Published: ${attempt.publishedAt!.toLocal()}',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade400,
                        ),
                      ),
                    ],
                    if (attempt.isFailed && attempt.errorMessage != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        attempt.errorMessage!,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFFF44336),
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: attempt.statusColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: attempt.statusColor),
                ),
                child: Text(
                  attempt.status,
                  style: TextStyle(
                    color: attempt.statusColor,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
