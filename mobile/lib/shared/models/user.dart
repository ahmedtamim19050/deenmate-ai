class AppUser {
  const AppUser({required this.id, required this.name, required this.email, this.country});

  final String id;
  final String name;
  final String email;
  final String? country;

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
    id: j['id'] as String,
    name: j['name'] as String,
    email: j['email'] as String,
    country: j['country'] as String?,
  );
}
